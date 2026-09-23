<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Graph inbox read. Attachments are not downloaded.
 * Sending stays on Laravel's mailer.
 */
final class GraphMailbox implements MailboxReader
{
    public static function credentialsPresent(): bool
    {
        foreach (['tenant', 'client_id', 'client_secret', 'mailbox'] as $key) {
            $value = config('services.graph.'.$key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    public function page(int $page, int $pageSize): array
    {
        if (! self::credentialsPresent()) {
            throw new RuntimeException('Microsoft Graph mailbox keys are empty.');
        }

        $mailbox = rawurlencode(trim((string) config('services.graph.mailbox')));
        $payload = Http::acceptJson()
            ->withToken($this->token())
            ->get('https://graph.microsoft.com/v1.0/users/'.$mailbox.'/mailFolders/inbox/messages', [
                '$select' => 'internetMessageId,subject,from,toRecipients,receivedDateTime,body,internetMessageHeaders',
                '$orderby' => 'receivedDateTime desc',
                '$top' => $pageSize,
                '$skip' => $page * $pageSize,
            ])
            ->throw()
            ->json('value');

        if (! is_array($payload)) {
            return [];
        }

        $mails = [];

        foreach ($payload as $row) {
            if (is_array($row)) {
                $mails[] = $this->message($row);
            }
        }

        usort($mails, fn (InboundMail $left, InboundMail $right): int => $right->sentAt <=> $left->sentAt);

        return $mails;
    }

    /**
     * @param  array<mixed>  $row
     */
    private function message(array $row): InboundMail
    {
        $headers = is_array($row['internetMessageHeaders'] ?? null) ? $row['internetMessageHeaders'] : [];
        $from = '';
        $fromBlock = $row['from'] ?? null;

        if (is_array($fromBlock)) {
            $email = $fromBlock['emailAddress'] ?? null;
            $address = is_array($email) ? ($email['address'] ?? '') : '';
            $from = is_string($address) ? $address : '';
        }

        $to = [];
        $recipients = $row['toRecipients'] ?? [];

        if (is_array($recipients)) {
            foreach ($recipients as $recipient) {
                if (! is_array($recipient)) {
                    continue;
                }

                $email = $recipient['emailAddress'] ?? null;
                $address = is_array($email) ? ($email['address'] ?? '') : '';

                if (is_string($address) && $address !== '') {
                    $to[] = $address;
                }
            }
        }

        $body = is_array($row['body'] ?? null) ? $row['body'] : [];
        $content = is_string($body['content'] ?? null) ? $body['content'] : '';
        $contentType = is_string($body['contentType'] ?? null) ? strtolower($body['contentType']) : 'text';
        $html = $contentType === 'html' ? $content : QuotedReply::htmlFromText($content);
        $text = $contentType === 'html' ? '' : $content;
        $subject = is_string($row['subject'] ?? null) ? $row['subject'] : '';
        $messageId = is_string($row['internetMessageId'] ?? null) ? $row['internetMessageId'] : null;
        $received = is_string($row['receivedDateTime'] ?? null) ? $row['receivedDateTime'] : null;

        return InboundMessages::make(
            messageIdHeader: $messageId,
            from: $from,
            to: $to,
            subject: $subject,
            bodyHtml: $html,
            bodyText: $text,
            inReplyTo: MailHeaders::value($headers, 'In-Reply-To'),
            referenceHeaders: $this->headerList(MailHeaders::value($headers, 'References')),
            sentAt: $received !== null ? CarbonImmutable::parse($received)->utc() : CarbonImmutable::now('UTC'),
        );
    }

    /**
     * @return list<string>
     */
    private function headerList(?string $value): array
    {
        return $value !== null && $value !== '' ? [$value] : [];
    }

    private function token(): string
    {
        $tenant = trim((string) config('services.graph.tenant'));
        $clientId = trim((string) config('services.graph.client_id'));
        $secret = trim((string) config('services.graph.client_secret'));
        $cacheKey = 'inbox.graph.token.'.hash('sha256', $tenant.'|'.$clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->post('https://login.microsoftonline.com/'.$tenant.'/oauth2/v2.0/token', [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ])
            ->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Microsoft Graph token response had no access_token.');
        }

        $expires = (int) $response->json('expires_in', 3600);
        Cache::put($cacheKey, $token, max(60, $expires - 60));

        return $token;
    }
}

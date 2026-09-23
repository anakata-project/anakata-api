<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

final class InboundMessages
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $referenceHeaders
     */
    public static function make(
        ?string $messageIdHeader,
        string $from,
        array $to,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        ?string $inReplyTo,
        array $referenceHeaders,
        CarbonImmutable $sentAt,
    ): InboundMail {
        $fromAddress = MailAddress::extract($from);
        $recipients = [];

        foreach ($to as $recipient) {
            $address = MailAddress::extract($recipient);

            if ($address !== '') {
                $recipients[] = $address;
            }
        }

        $references = [];

        foreach ($referenceHeaders as $header) {
            foreach (InboundMessageId::ids($header) as $id) {
                $references[] = $id;
            }
        }

        $replyTo = InboundMessageId::normalise($inReplyTo);

        return new InboundMail(
            messageId: InboundMessageId::canonical($messageIdHeader, $fromAddress, $sentAt, $subject, $bodyHtml),
            from: $fromAddress,
            to: array_values(array_unique($recipients)),
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            inReplyTo: $replyTo,
            references: array_values(array_unique($references)),
            sentAt: $sentAt,
        );
    }
}

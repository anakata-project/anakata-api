<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

final readonly class InboundMail
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $references
     */
    public function __construct(
        public string $messageId,
        public string $from,
        public array $to,
        public string $subject,
        public string $bodyHtml,
        public string $bodyText,
        public ?string $inReplyTo,
        public array $references,
        public CarbonImmutable $sentAt,
    ) {}
}

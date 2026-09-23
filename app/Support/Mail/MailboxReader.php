<?php

declare(strict_types=1);

namespace App\Support\Mail;

interface MailboxReader
{
    /**
     * Newest first. Attachments are not fetched.
     *
     * @return list<InboundMail>
     */
    public function page(int $page, int $pageSize): array;
}

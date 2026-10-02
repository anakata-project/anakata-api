<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReportReady extends Notification
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $title,
        public string $sentence,
        public string $window,
        public int $rows,
        public string $url,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->theme('anakata')
            ->markdown('mail.reports.notification', [
                'window' => $this->window,
                'rows' => $this->rows,
            ])
            ->from((string) config('mail.from.address'), 'Anakata')
            ->subject($this->subject)
            ->greeting($this->title)
            ->line($this->sentence)
            ->action('Open in the panel', $this->url);

        $path = $this->attachmentPath;
        $name = $this->attachmentName;

        if ($path !== null && $name !== null) {
            $message->line('The report file is attached.')
                ->attachFromStorageDisk('reports', $path, $name);
        }

        return $message;
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CopilotTitleAgent;
use App\Events\CopilotConversationTitled;
use App\Models\CopilotConversation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class GenerateCopilotConversationTitle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $message,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return 'copilot-title:'.$this->conversationId;
    }

    public function handle(): void
    {
        $conversation = $this->untitledConversation();

        if ($conversation === null) {
            return;
        }

        $title = $this->generate();

        $conversation = $this->untitledConversation();

        if ($conversation === null) {
            return;
        }

        $conversation->forceFill(['title' => $title])->save();

        event(new CopilotConversationTitled(
            conversationId: $conversation->id,
            title: $title,
        ));
    }

    private function untitledConversation(): ?CopilotConversation
    {
        $conversation = CopilotConversation::query()->find($this->conversationId);

        if (! $conversation instanceof CopilotConversation || $conversation->title !== CopilotConversation::UNTITLED) {
            return null;
        }

        return $conversation;
    }

    private function generate(): string
    {
        try {
            $response = (new CopilotTitleAgent)->prompt(
                Str::limit($this->message, 500),
                timeout: 20,
            );
        } catch (Throwable) {
            return $this->fallback();
        }

        return $this->normalize((string) $response->text);
    }

    private function normalize(string $raw): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        $title = trim($title, " \t\n\r\0\x0B\"'`");

        if ($title === '') {
            return $this->fallback();
        }

        return Str::limit($title, 100, '');
    }

    private function fallback(): string
    {
        return Str::limit($this->message, 50, '...');
    }
}

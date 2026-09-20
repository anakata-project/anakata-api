<?php

declare(strict_types=1);

namespace App\Actions\Waitlist;

use App\Actions\Action;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class RemoveWaitlistEntry extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WaitlistEntry $entry, array $data, User $actor): WaitlistEntry
    {
        return $this->transaction(function () use ($entry, $data, $actor): WaitlistEntry {
            $entry->refresh();

            if ($entry->removed_at !== null) {
                throw ValidationException::withMessages([
                    'reason' => ['This waitlist entry has already been removed.'],
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? ''));

            $entry->removed_at = now();
            $entry->removed_by = $actor->id;
            $entry->removed_reason = $reason;
            $entry->save();

            History::record($entry, 'waitlist.removed', after: [
                'removed_by' => $actor->id,
            ], reason: $reason, actor: $actor);

            return $entry->refresh()->load(['departure.yacht', 'contact', 'removedBy']);
        });
    }
}

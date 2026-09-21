<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Models\Contact;
use App\Models\ContactMerge;
use App\Models\User;
use App\Support\Crm\ContactReferences;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class UndoContactMerge extends Action
{
    /**
     * @return array{merge: ContactMerge, skipped_rows: list<array{table: string, id: int}>}
     */
    public function handle(ContactMerge $merge, string $reason, User $actor): array
    {
        return $this->transaction(function () use ($merge, $reason, $actor): array {
            $locked = ContactMerge::query()->whereKey($merge->id)->lockForUpdate()->firstOrFail();

            if ($locked->undone_at !== null) {
                throw new HttpException(422, 'This merge has already been undone.');
            }

            if ($locked->erased_at !== null) {
                throw new HttpException(422, 'This merge has been erased and cannot be undone.');
            }

            if (now()->greaterThanOrEqualTo($locked->merged_at->copy()->addDays(30))) {
                throw new HttpException(422, 'This merge is permanent.');
            }

            $later = ContactMerge::query()
                ->where('loser_id', $locked->survivor_id)
                ->whereNull('undone_at')
                ->where('id', '>', $locked->id)
                ->orderBy('id')
                ->first();

            if ($later instanceof ContactMerge) {
                throw new HttpException(
                    422,
                    'Undo merge #'.$later->id.' first — this survivor was later merged onward.',
                );
            }

            $lowerId = min($locked->survivor_id, $locked->loser_id);
            $higherId = max($locked->survivor_id, $locked->loser_id);
            $first = Contact::query()->whereKey($lowerId)->lockForUpdate()->firstOrFail();
            $second = Contact::query()->whereKey($higherId)->lockForUpdate()->firstOrFail();
            $survivor = $first->id === $locked->survivor_id ? $first : $second;
            $loser = $first->id === $locked->loser_id ? $first : $second;

            $repointed = $locked->repointed_rows ?? [];
            $skipped = ContactReferences::restoreIfStillOn($repointed, $survivor->id, $loser->id);

            $this->restoreFields($survivor, $loser, $locked, $actor);

            $historyReason = $reason;
            if ($skipped !== []) {
                $names = array_map(
                    fn (array $row): string => $row['table'].'#'.$row['id'],
                    $skipped,
                );
                $historyReason .= ' Skipped: '.implode(', ', $names).'.';
            }

            ContactMerge::query()->whereKey($locked->id)->update([
                'undone_at' => now(),
                'undone_by' => $actor->id,
                'undo_reason' => $reason,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);

            Contact::query()->whereKey($loser->id)->update([
                'merged_into_id' => null,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $after = [
                'merge_id' => $locked->id,
                'survivor_id' => $survivor->id,
                'loser_id' => $loser->id,
                'skipped' => $skipped,
            ];

            History::record($survivor, 'contact.unmerged', after: $after, reason: $historyReason, actor: $actor);
            History::record($loser, 'contact.unmerged', after: $after, reason: $historyReason, actor: $actor);

            return [
                'merge' => $locked->fresh() ?? $locked,
                'skipped_rows' => $skipped,
            ];
        });
    }

    private function restoreFields(Contact $survivor, Contact $loser, ContactMerge $merge, User $actor): void
    {
        $loserFields = $merge->loser_fields ?? [];
        $filled = $merge->survivor_filled ?? [];

        if (array_key_exists('email', $filled) && $survivor->email === $filled['email']) {
            Contact::query()->whereKey($survivor->id)->update([
                'email' => null,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $survivor->email = null;
        }

        $loserUpdate = [];
        foreach ($loserFields as $field => $value) {
            $loserUpdate[$field] = $value;
        }

        if ($loserUpdate !== []) {
            Contact::query()->whereKey($loser->id)->update([
                ...self::encodeAttributes($loserUpdate),
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);
        }

        $survivorRevert = [];
        foreach ($filled as $field => $value) {
            if ($field === 'email') {
                continue;
            }

            $current = $survivor->getAttribute($field);

            if ($current === $value) {
                $survivorRevert[$field] = null;
            }
        }

        if ($survivorRevert !== []) {
            Contact::query()->whereKey($survivor->id)->update([
                ...$survivorRevert,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);
        }

        $survivor->refresh();
        $loser->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function encodeAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = json_encode($value);
            }
        }

        return $attributes;
    }
}

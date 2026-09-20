<?php

declare(strict_types=1);

namespace App\Actions\Blocks;

use App\Actions\Action;
use App\Models\InternalBlock;
use App\Support\History\History;

final class UpdateInternalBlockNotes extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalBlock $block, array $data): InternalBlock
    {
        return $this->transaction(function () use ($block, $data): InternalBlock {
            if (array_key_exists('reason', $data)) {
                $block->reason = $data['reason'];
            }

            if (array_key_exists('notes', $data)) {
                $block->notes = $data['notes'];
            }

            if (! $block->isDirty()) {
                return $block;
            }

            $block->save();

            $changes = $block->getChanges();
            $previous = $block->getPrevious();
            $before = [];
            $after = [];

            foreach (['reason', 'notes'] as $field) {
                if (! array_key_exists($field, $changes)) {
                    continue;
                }

                $before[$field] = $previous[$field] ?? null;
                $after[$field] = $changes[$field];
            }

            if ($before !== []) {
                History::record($block, 'block.updated', $before, $after);
            }

            return $block;
        });
    }
}

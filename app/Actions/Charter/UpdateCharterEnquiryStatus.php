<?php

declare(strict_types=1);

namespace App\Actions\Charter;

use App\Actions\Action;
use App\Enums\CharterEnquiryStatus;
use App\Exceptions\ConflictException;
use App\Models\CharterEnquiry;
use App\Models\User;
use App\Support\History\History;

final class UpdateCharterEnquiryStatus extends Action
{
    public function handle(CharterEnquiry $enquiry, CharterEnquiryStatus $status, User $actor): CharterEnquiry
    {
        return $this->transaction(function () use ($enquiry, $status, $actor): CharterEnquiry {
            $enquiry = CharterEnquiry::query()->whereKey($enquiry->id)->lockForUpdate()->firstOrFail();

            if ($enquiry->status === $status) {
                return $enquiry;
            }

            if (! $this->allowed($enquiry->status, $status)) {
                throw new ConflictException('That status change is not allowed.');
            }

            $before = $enquiry->status;
            $enquiry->status = $status;
            $enquiry->save();

            History::record($enquiry, 'charter_enquiry.status_changed', before: [
                'status' => $before->value,
            ], after: [
                'status' => $status->value,
            ], actor: $actor);

            return $enquiry->refresh()->load(['contact', 'departure']);
        });
    }

    private function allowed(CharterEnquiryStatus $from, CharterEnquiryStatus $to): bool
    {
        return match ($from) {
            CharterEnquiryStatus::New => $to === CharterEnquiryStatus::Contacted,
            CharterEnquiryStatus::Contacted => $to === CharterEnquiryStatus::Closed,
            CharterEnquiryStatus::Closed => false,
        };
    }
}

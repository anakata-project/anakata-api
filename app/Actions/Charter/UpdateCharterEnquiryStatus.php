<?php

declare(strict_types=1);

namespace App\Actions\Charter;

use App\Actions\Action;
use App\Enums\CharterEnquiryStatus;
use App\Exceptions\ConflictException;
use App\Models\CharterEnquiry;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class UpdateCharterEnquiryStatus extends Action
{
    public function handle(CharterEnquiry $enquiry, CharterEnquiryStatus $status, User $actor, ?string $reason = null): CharterEnquiry
    {
        return $this->transaction(function () use ($enquiry, $status, $actor, $reason): CharterEnquiry {
            $enquiry = CharterEnquiry::query()->whereKey($enquiry->id)->lockForUpdate()->firstOrFail();

            if ($enquiry->status === $status) {
                return $enquiry;
            }

            if (! $this->allowed($enquiry, $status)) {
                throw new ConflictException('That status change is not allowed.');
            }

            if (in_array($status, [CharterEnquiryStatus::Declined, CharterEnquiryStatus::Closed], true)
                && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required.'],
                ]);
            }

            $before = $enquiry->status;
            $enquiry->status = $status;
            $enquiry->save();

            History::record($enquiry, 'charter_enquiry.status_changed', before: [
                'status' => $before->value,
            ], after: [
                'status' => $status->value,
            ], reason: $reason !== null && trim($reason) !== '' ? trim($reason) : null, actor: $actor);

            return $enquiry->refresh()->load(['contact', 'departure']);
        });
    }

    private function allowed(CharterEnquiry $enquiry, CharterEnquiryStatus $to): bool
    {
        return match ($enquiry->status) {
            CharterEnquiryStatus::New => in_array($to, [CharterEnquiryStatus::Contacted, CharterEnquiryStatus::Closed], true),
            CharterEnquiryStatus::Contacted => in_array($to, [CharterEnquiryStatus::Quoted, CharterEnquiryStatus::Closed], true),
            CharterEnquiryStatus::Quoted => in_array($to, [CharterEnquiryStatus::Declined, CharterEnquiryStatus::Closed], true),
            CharterEnquiryStatus::Declined => $to === CharterEnquiryStatus::Closed,
            CharterEnquiryStatus::Accepted => $to === CharterEnquiryStatus::Closed && $enquiry->booking_id === null,
            CharterEnquiryStatus::Closed => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Actions\Action;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class ApproveOffer extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Offer $offer, array $data, User $actor): Offer
    {
        return $this->transaction(function () use ($offer, $data, $actor): Offer {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($offer->status !== OfferStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only a pending offer can be approved.'],
                ]);
            }

            $reason = trim((string) $data['reason']);

            $offer->status = OfferStatus::Live;
            $offer->approved_by = $actor->id;
            $offer->approved_at = now();
            $offer->approval_reason = $reason;
            $offer->needs_reapproval = false;
            $offer->first_live_at ??= now();
            $offer->save();

            History::record($offer, 'offer.approved', before: [
                'status' => OfferStatus::Pending->value,
            ], after: [
                'status' => OfferStatus::Live->value,
            ], reason: $reason, actor: $actor);

            // TODO(task 03) bump the engine feed version on offer approval

            return $offer->fresh(['approvedBy']) ?? $offer;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Actions\Action;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class RejectOffer extends Action
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
                    'status' => ['Only a pending offer can be rejected.'],
                ]);
            }

            $reason = trim((string) $data['reason']);

            $offer->status = OfferStatus::Draft;
            $offer->approval_reason = $reason;
            $offer->save();

            History::record($offer, 'offer.rejected', before: [
                'status' => OfferStatus::Pending->value,
            ], after: [
                'status' => OfferStatus::Draft->value,
            ], reason: $reason, actor: $actor);

            return $offer->fresh(['approvedBy']) ?? $offer;
        });
    }
}

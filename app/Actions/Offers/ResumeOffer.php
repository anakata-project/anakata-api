<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Actions\Action;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class ResumeOffer extends Action
{
    public function handle(Offer $offer, User $actor): Offer
    {
        return $this->transaction(function () use ($offer, $actor): Offer {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($offer->status !== OfferStatus::Paused) {
                throw ValidationException::withMessages([
                    'status' => ['Only a paused offer can be resumed.'],
                ]);
            }

            $next = OfferStatus::Live;

            if ($offer->type->isPriceAffecting() && $offer->needs_reapproval) {
                $next = OfferStatus::Pending;
            }

            $offer->status = $next;
            $offer->save();

            History::record($offer, 'offer.resumed', before: [
                'status' => OfferStatus::Paused->value,
            ], after: [
                'status' => $next->value,
            ], actor: $actor);

            // TODO(task 03) bump the engine feed version on offer resume

            return $offer->fresh(['approvedBy']) ?? $offer;
        });
    }
}

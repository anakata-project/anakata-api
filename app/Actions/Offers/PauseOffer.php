<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Actions\Action;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Services\Engine\EngineFeedVersion;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class PauseOffer extends Action
{
    public function handle(Offer $offer, User $actor): Offer
    {
        $offer = $this->transaction(function () use ($offer, $actor): Offer {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($offer->status !== OfferStatus::Live) {
                throw ValidationException::withMessages([
                    'status' => ['Only a live offer can be paused.'],
                ]);
            }

            $offer->status = OfferStatus::Paused;
            $offer->save();

            History::record($offer, 'offer.paused', before: [
                'status' => OfferStatus::Live->value,
            ], after: [
                'status' => OfferStatus::Paused->value,
            ], actor: $actor);

            return $offer->fresh(['approvedBy']) ?? $offer;
        });

        EngineFeedVersion::bump();

        return $offer;
    }
}

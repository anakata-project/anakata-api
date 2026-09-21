<?php

declare(strict_types=1);

namespace App\Actions\Offers;

use App\Actions\Action;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Services\Engine\EngineFeedVersion;
use App\Support\History\History;
use App\Support\Offers\OfferFields;
use App\Support\Offers\OfferGuardrails;
use Illuminate\Validation\ValidationException;

final class UpdateOffer extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Offer $offer, array $data, User $actor): Offer
    {
        $shouldBump = false;

        $offer = $this->transaction(function () use ($offer, $data, $actor, &$shouldBump): Offer {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $wasPublic = $offer->status === OfferStatus::Live && $offer->enginePlacement() !== 'not_public';

            $data = OfferGuardrails::normalize($data);
            $asDraft = array_key_exists('as_draft', $data) ? (bool) $data['as_draft'] : false;
            unset($data['as_draft']);

            if ($asDraft && in_array($offer->status, [OfferStatus::Live, OfferStatus::Paused], true)) {
                throw ValidationException::withMessages([
                    'as_draft' => ['A live or paused offer cannot be saved as a draft. Pause it, or submit the change for approval.'],
                ]);
            }

            $changed = OfferFields::changed($offer, $data);
            $before = $changed['before'];
            $after = $changed['after'];
            $material = OfferFields::materialChanged($before);

            $statusBefore = $offer->status;
            $event = 'offer.updated';

            if ($asDraft) {
                $offer->status = OfferStatus::Draft;
            } else {
                if ($offer->type->isPriceAffecting()) {
                    if ($statusBefore === OfferStatus::Live && $material) {
                        $offer->status = OfferStatus::Pending;
                        $event = 'offer.submitted';
                    } elseif ($statusBefore === OfferStatus::Paused && $material) {
                        $offer->needs_reapproval = true;
                    } elseif (in_array($statusBefore, [OfferStatus::Draft, OfferStatus::Pending], true)) {
                        $offer->status = OfferStatus::Pending;

                        if ($statusBefore === OfferStatus::Draft) {
                            $event = 'offer.submitted';
                        }
                    }
                } else {
                    $offer->status = OfferStatus::Live;
                    $offer->first_live_at ??= now();
                    $offer->needs_reapproval = false;
                }
            }

            if ($before === [] && $offer->status === $statusBefore && ! $offer->isDirty('needs_reapproval')) {
                return $offer;
            }

            if ($offer->status !== $statusBefore) {
                $before['status'] = $statusBefore->value;
                $after['status'] = $offer->status->value;
            }

            if ($before === [] && $offer->isDirty('needs_reapproval')) {
                $before['needs_reapproval'] = false;
                $after['needs_reapproval'] = $offer->needs_reapproval;
            }

            $offer->save();

            History::record($offer, $event, before: $before, after: $after, actor: $actor);

            $fresh = $offer->fresh(['approvedBy']) ?? $offer;
            $isPublic = $fresh->status === OfferStatus::Live && $fresh->enginePlacement() !== 'not_public';
            $shouldBump = $wasPublic || $isPublic;

            return $fresh;
        });

        if ($shouldBump) {
            EngineFeedVersion::bump();
        }

        return $offer;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CreateUnboundDeal extends Action
{
    public function handle(
        Contact $contact,
        User $actor,
        string $title,
        DealType $type,
        DealStage $stage,
        ?int $estimate,
        ?string $notes,
    ): Deal {
        if (! $stage->isOpen()) {
            throw ValidationException::withMessages([
                'stage' => ['Choose a stage from new lead through negotiation.'],
            ]);
        }

        return $this->transaction(function () use ($contact, $actor, $title, $type, $stage, $estimate, $notes): Deal {
            $resolved = Contact::resolveIdentity($contact->id) ?? $contact;

            $deal = Deal::query()->create([
                'contact_id' => $resolved->id,
                'owner_id' => $actor->id,
                'title' => $title,
                'type' => $type,
                'stage' => $stage,
                'stage_entered_at' => Carbon::now(),
                'estimate' => $estimate,
                'notes' => $notes,
            ]);

            History::record($deal, 'deal.created', after: [
                'title' => $deal->title,
                'type' => $deal->type->value,
                'stage' => $stage->value,
            ]);

            return $deal->refresh();
        });
    }
}

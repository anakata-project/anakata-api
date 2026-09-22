<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\ActivityKind;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\Deal;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RecordContactActivity extends Action
{
    public function handle(Contact $contact, ActivityKind $kind, string $body, ?int $dealId): ContactActivity
    {
        return $this->transaction(function () use ($contact, $kind, $body, $dealId): ContactActivity {
            $resolved = Contact::resolveIdentity($contact->id) ?? $contact;
            $deal = null;

            if ($dealId !== null) {
                $deal = Deal::query()->find($dealId);

                if (! $deal instanceof Deal || $deal->contact_id !== $resolved->id) {
                    throw ValidationException::withMessages([
                        'deal_id' => ['That deal belongs to another contact.'],
                    ]);
                }
            }

            return ContactActivity::query()->create([
                'contact_id' => $resolved->id,
                'kind' => $kind,
                'body' => $body,
                'occurred_at' => Carbon::now(),
                'deal_id' => $deal?->id,
            ]);
        });
    }
}

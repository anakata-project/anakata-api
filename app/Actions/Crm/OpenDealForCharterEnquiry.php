<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\CharterEnquiry;
use App\Models\Contact;
use App\Models\Deal;
use App\Support\History\History;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class OpenDealForCharterEnquiry extends Action
{
    public function handle(CharterEnquiry $enquiry): Deal
    {
        try {
            return $this->transaction(fn (): Deal => $this->open($enquiry));
        } catch (UniqueConstraintViolationException $exception) {
            $found = Deal::query()->where('charter_enquiry_id', $enquiry->id)->first();

            if ($found instanceof Deal) {
                return $found;
            }

            throw $exception;
        }
    }

    private function open(CharterEnquiry $enquiry): Deal
    {
        $existing = Deal::query()->where('charter_enquiry_id', $enquiry->id)->first();

        if ($existing instanceof Deal) {
            return $existing;
        }

        $contact = Contact::resolveIdentity($enquiry->contact_id) ?? $enquiry->contact;

        $deal = Deal::query()->create([
            'contact_id' => $contact->id,
            'owner_id' => null,
            'title' => $contact->name,
            'type' => DealType::Charter,
            'stage' => DealStage::NewLead,
            'stage_entered_at' => Carbon::now(),
            'charter_enquiry_id' => $enquiry->id,
        ]);

        History::record($deal, 'deal.created', after: [
            'title' => $deal->title,
            'type' => $deal->type->value,
            'stage' => DealStage::NewLead->value,
            'charter_enquiry_id' => $enquiry->id,
        ], system: true);

        return $deal->refresh();
    }
}

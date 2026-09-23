<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\JourneyEnrolment;
use App\Models\JourneySend;
use App\Models\JourneyStep;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JourneyEnrolment
 */
#[SchemaName('CrmJourneyEnrolmentResource')]
class CrmJourneyEnrolmentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrolment = $this->resource;

        if (! $enrolment instanceof JourneyEnrolment) {
            return [];
        }

        $enrolment->loadMissing(['contact', 'booking', 'journey.steps', 'sends']);
        $step = $enrolment->journey->steps->firstWhere('position', $enrolment->position);

        return [
            'id' => $enrolment->id,
            'journey_key' => $enrolment->journey->key,
            'contact' => [
                'id' => $enrolment->contact->id,
                'name' => $enrolment->contact->name,
                'email' => $enrolment->contact->email,
            ],
            'booking' => $enrolment->booking === null ? null : [
                'id' => $enrolment->booking->id,
                'reference' => $enrolment->booking->reference,
            ],
            'step' => $step instanceof JourneyStep ? [
                'position' => $step->position,
                'name' => $step->name,
                'template_key' => $step->template_key,
            ] : null,
            'next_due_at' => Iso::utc($enrolment->next_due_at),
            'status' => $enrolment->status->value,
            'exit_reason' => $enrolment->exit_reason,
            'enrolled_at' => Iso::utc($enrolment->enrolled_at),
            'exited_at' => Iso::utc($enrolment->exited_at),
            'sends' => $enrolment->sends->map(fn (JourneySend $send): array => [
                'sent_at' => Iso::utc($send->sent_at),
                'template_key' => $send->template_key,
                'catalogue_key' => $send->catalogue_key,
                'delivery_id' => $send->delivery_id,
            ])->all(),
        ];
    }
}

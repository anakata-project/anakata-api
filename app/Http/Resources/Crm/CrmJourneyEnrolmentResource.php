<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\JourneyEnrolmentStatus;
use App\Models\JourneyEnrolment;
use App\Models\JourneySend;
use App\Models\JourneyStep;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @mixin JourneyEnrolment
 */
#[SchemaName('CrmJourneyEnrolmentResource')]
class CrmJourneyEnrolmentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     journey_key: string,
     *     contact: array{id: int, name: string, email: string|null},
     *     booking: array{id: int, reference: string|null}|null,
     *     step: array{position: int, name: string, template_key: string}|null,
     *     next_due_at: string|null,
     *     status: JourneyEnrolmentStatus,
     *     exit_reason: string|null,
     *     enrolled_at: string|null,
     *     exited_at: string|null,
     *     sends: list<array{sent_at: string|null, template_key: string, catalogue_key: string|null, delivery_id: int|null}>
     * }
     */
    public function toArray(Request $request): array
    {
        $enrolment = $this->resource;

        if (! $enrolment instanceof JourneyEnrolment) {
            throw new LogicException('Journey enrolment resource expected an enrolment.');
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
            'status' => $this->status($enrolment),
            'exit_reason' => $enrolment->exit_reason,
            'enrolled_at' => Iso::utc($enrolment->enrolled_at),
            'exited_at' => Iso::utc($enrolment->exited_at),
            'sends' => $this->sends($enrolment->sends),
        ];
    }

    private function status(JourneyEnrolment $enrolment): JourneyEnrolmentStatus
    {
        return $enrolment->status;
    }

    /**
     * @param  Collection<int, JourneySend>  $sends
     * @return list<array{sent_at: string|null, template_key: string, catalogue_key: string|null, delivery_id: int|null}>
     */
    private function sends(Collection $sends): array
    {
        $rows = [];

        foreach ($sends as $send) {
            $rows[] = $this->send($send);
        }

        return $rows;
    }

    /**
     * @return array{sent_at: string|null, template_key: string, catalogue_key: string|null, delivery_id: int|null}
     */
    private function send(JourneySend $send): array
    {
        return [
            'sent_at' => Iso::utc($send->sent_at),
            'template_key' => $send->template_key,
            'catalogue_key' => $send->catalogue_key,
            'delivery_id' => $send->delivery_id,
        ];
    }
}

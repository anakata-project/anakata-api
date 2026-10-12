<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use Carbon\CarbonImmutable;

final class DealCard
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     contact: array{id: int, name: string},
     *     type: string,
     *     owner: array{id: int, name: string}|null,
     *     value: int,
     *     value_label: string,
     *     sla_state: string|null,
     *     booking: array{reference: string, status: string, departure_date: string}|null,
     *     may_move: bool
     * }
     */
    public static function fromRow(object $row, User $actor): array
    {
        $stage = DealStage::tryFrom((string) ($row->projected_stage ?? ''));
        $value = (int) $row->value_amount;
        $entered = CarbonImmutable::parse((string) $row->stage_entered_at);
        $rules = app(CurrentConfig::class)->businessRules();
        $slaState = $stage instanceof DealStage && $stage->isOpen()
            ? DealSla::assess($stage, $entered, CarbonImmutable::now(), $rules)
            : null;
        $ownerId = $row->owner_id !== null ? (int) $row->owner_id : null;
        $bound = $row->booking_id !== null || $row->group_id !== null;

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'contact' => [
                'id' => (int) $row->contact_id,
                'name' => (string) $row->contact_name,
            ],
            'type' => (string) $row->type,
            'owner' => $ownerId === null ? null : [
                'id' => $ownerId,
                'name' => (string) $row->owner_name,
            ],
            'value' => $value,
            'value_label' => (string) $row->value_label,
            'sla_state' => $slaState,
            'booking' => self::booking($row),
            'may_move' => self::mayMove($actor, $ownerId, $bound),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     stage: string,
     *     stage_label: string,
     *     contact: array{id: int, name: string},
     *     type: string,
     *     owner: array{id: int, name: string}|null,
     *     value: int,
     *     value_label: string,
     *     sla_state: string|null,
     *     booking: array{reference: string, status: string, departure_date: string}|null,
     *     may_move: bool
     * }
     */
    public static function listRow(object $row, User $actor): array
    {
        $stage = DealStage::tryFrom((string) ($row->projected_stage ?? ''));

        return [
            ...self::fromRow($row, $actor),
            'stage' => $stage instanceof DealStage ? $stage->value : (string) ($row->projected_stage ?? ''),
            'stage_label' => $stage instanceof DealStage ? $stage->label() : '',
        ];
    }

    /**
     * @return array{reference: string, status: string, departure_date: string}|null
     */
    private static function booking(object $row): ?array
    {
        $reference = is_string($row->booking_reference ?? null) ? $row->booking_reference : '';

        if ($reference === '') {
            return null;
        }

        $date = $row->departure_date ?? null;

        return [
            'reference' => $reference,
            'status' => is_string($row->booking_status ?? null) ? $row->booking_status : '',
            'departure_date' => is_string($date) ? substr($date, 0, 10) : '',
        ];
    }

    private static function mayMove(User $actor, ?int $ownerId, bool $bound): bool
    {
        if ($bound || $ownerId === null || ! $actor->hasPermission(Permission::PipelineMoveStage)) {
            return false;
        }

        return $ownerId === $actor->id || $actor->hasPermission(Permission::RecordsActOnAny);
    }
}

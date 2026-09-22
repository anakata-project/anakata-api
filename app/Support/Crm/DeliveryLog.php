<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTriggeredBy;
use App\Enums\DocumentKind;
use App\Models\Delivery;
use App\Support\BusinessTime;
use App\Support\Iso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class DeliveryLog
{
    public const QUEUED_MINUTES = 15;

    public const ENGAGEMENT = 'Opens and downloads are not tracked (LEG-002)';

    /**
     * @param  array{status?: string|null, kind?: string|null, from?: string|null, to?: string|null, booking?: string|null, contact?: string|null}  $filters
     * @return array{page: LengthAwarePaginator<int, Delivery>, kpis: array{sent_today: int, failed: int, blocked: int, queued_over_15_minutes: int}}
     */
    public static function page(array $filters, int $perPage): array
    {
        $query = self::base();
        self::apply($query, $filters);

        return [
            'page' => $query->orderByDesc('deliveries.id')->paginate($perPage),
            'kpis' => self::kpis(),
        ];
    }

    /**
     * @return array{sent_today: int, failed: int, blocked: int, queued_over_15_minutes: int}
     */
    public static function kpis(): array
    {
        $start = BusinessTime::now()->startOfDay()->utc();
        $end = BusinessTime::now()->endOfDay()->utc();
        $queuedBefore = now()->subMinutes(self::QUEUED_MINUTES);

        $row = Delivery::query()->toBase()
            ->selectRaw('SUM(CASE WHEN status = ? AND sent_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as sent_today', [DeliveryStatus::Sent->value, $start, $end])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [DeliveryStatus::Failed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as blocked', [DeliveryStatus::Blocked->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND created_at < ? THEN 1 ELSE 0 END) as queued_over', [DeliveryStatus::Queued->value, $queuedBefore])
            ->first();

        return [
            'sent_today' => (int) ($row->sent_today ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'blocked' => (int) ($row->blocked ?? 0),
            'queued_over_15_minutes' => (int) ($row->queued_over ?? 0),
        ];
    }

    /**
     * @return Builder<Delivery>
     */
    private static function base(): Builder
    {
        return Delivery::query()
            ->join('bookings', 'bookings.id', '=', 'deliveries.booking_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'bookings.contact_id')
            ->leftJoin('documents', 'documents.id', '=', 'deliveries.document_id')
            ->leftJoin('users', 'users.id', '=', 'deliveries.created_by')
            ->select([
                'deliveries.id',
                'deliveries.booking_id',
                'deliveries.kind',
                'deliveries.status',
                'deliveries.error',
                'deliveries.blocked_reason',
                'deliveries.sent_at',
                'deliveries.created_at',
                'deliveries.triggered_by',
                'bookings.reference as booking_reference',
                'contacts.name as client_name',
                'documents.version as document_version',
                'documents.reason as document_reason',
                'documents.kind as document_kind',
                'users.name as actor_name',
            ])
            ->selectRaw('COALESCE(JSON_LENGTH(deliveries.to), 0) as recipient_count')
            ->selectRaw('EXISTS (
                SELECT 1 FROM documents later
                WHERE later.booking_id = documents.booking_id
                  AND later.kind = documents.kind
                  AND later.version > documents.version
            ) as superseded');
    }

    /**
     * @param  Builder<Delivery>  $query
     * @param  array{status?: string|null, kind?: string|null, from?: string|null, to?: string|null, booking?: string|null, contact?: string|null}  $filters
     */
    private static function apply(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('deliveries.status', $status);
        }

        $kind = $filters['kind'] ?? null;
        if (is_string($kind) && $kind !== '') {
            $query->where('deliveries.kind', $kind);
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if (is_string($from) && $from !== '') {
            $query->whereRaw('COALESCE(deliveries.sent_at, deliveries.created_at) >= ?', [BusinessTime::calendarDay($from)->utc()]);
        }
        if (is_string($to) && $to !== '') {
            $query->whereRaw('COALESCE(deliveries.sent_at, deliveries.created_at) <= ?', [BusinessTime::calendarDay($to)->endOfDay()->utc()]);
        }

        $booking = $filters['booking'] ?? null;
        if (is_string($booking) && $booking !== '') {
            $query->where('bookings.reference', $booking);
        }

        $contact = $filters['contact'] ?? null;
        if (is_string($contact) && $contact !== '') {
            $query->where('contacts.name', 'like', '%'.$contact.'%');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Delivery $delivery): array
    {
        $documentKind = $delivery->getAttribute('document_kind');
        $hasDocument = is_string($documentKind) && $documentKind !== '';
        $error = is_string($delivery->error) ? strtok($delivery->error, "\n") : null;
        $detail = is_string($delivery->blocked_reason) && $delivery->blocked_reason !== ''
            ? $delivery->blocked_reason
            : ($error === false ? null : $error);
        $triggered = $delivery->triggered_by === DeliveryTriggeredBy::System
            ? 'System'
            : (is_string($delivery->getAttribute('actor_name')) ? $delivery->getAttribute('actor_name') : 'User');
        $at = $delivery->sent_at ?? $delivery->created_at;

        return [
            'id' => $delivery->id,
            'booking' => [
                'id' => $delivery->booking_id,
                'reference' => $delivery->getAttribute('booking_reference'),
            ],
            'client' => $delivery->getAttribute('client_name'),
            'document' => $hasDocument ? [
                'kind' => $documentKind,
                'label' => DocumentKind::from($documentKind)->label(),
                'version' => (int) $delivery->getAttribute('document_version'),
                'reason' => $delivery->getAttribute('document_reason'),
            ] : null,
            'delivery_kind' => $delivery->kind->value,
            'delivery_kind_label' => $delivery->kind->label(),
            'channel' => 'EMAIL',
            'status' => $delivery->status->value,
            'at' => Iso::utc($at),
            'detail' => $detail,
            'recipient_count' => (int) $delivery->getAttribute('recipient_count'),
            'triggered_by' => $triggered,
            'superseded' => (bool) $delivery->getAttribute('superseded'),
            'rms_path' => '/rms/operations/documents?booking='.$delivery->booking_id,
        ];
    }
}

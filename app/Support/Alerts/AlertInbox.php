<?php

declare(strict_types=1);

namespace App\Support\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AlertInbox
{
    /**
     * @param  array{state: string|null, severity: string|null, kind: string|null, section: string|null}  $filters
     * @return array{
     *     page: LengthAwarePaginator<int, Alert>,
     *     counts: array{INFO: int, WARN: int, CRITICAL: int}
     * }
     */
    public static function build(User $actor, array $filters): array
    {
        $visible = AlertRegistry::visibleTo($actor);
        $listed = self::kindsForSection($visible, $filters['section']);

        $query = self::base($listed);
        self::applyState($query, $filters['state']);

        if ($filters['severity'] !== null) {
            $query->where('severity', $filters['severity']);
        }

        if ($filters['kind'] !== null) {
            $query->where('kind', $filters['kind']);
        }

        $page = $query
            ->with([
                'booking',
                'payment.booking',
                'delivery.booking',
                'crmTask',
                'notifications.user',
            ])
            ->orderByDesc('raised_at')
            ->orderByDesc('id')
            ->paginate(25);

        return [
            'page' => $page,
            'counts' => self::counts($visible),
        ];
    }

    /**
     * @param  list<AlertKind>  $visible
     * @return array{INFO: int, WARN: int, CRITICAL: int}
     */
    public static function counts(array $visible): array
    {
        $counts = [
            AlertSeverity::Info->value => 0,
            AlertSeverity::Warn->value => 0,
            AlertSeverity::Critical->value => 0,
        ];

        if ($visible === []) {
            return $counts;
        }

        $rows = Alert::query()
            ->open()
            ->whereIn('kind', array_map(fn (AlertKind $kind): string => $kind->value, $visible))
            ->selectRaw('severity, COUNT(*) as aggregate')
            ->groupBy('severity')
            ->get();

        foreach ($rows as $row) {
            $counts[$row->severity->value] = (int) $row->getAttribute('aggregate');
        }

        return $counts;
    }

    /**
     * @param  list<AlertKind>  $visible
     * @return list<AlertKind>
     */
    private static function kindsForSection(array $visible, ?string $section): array
    {
        if ($section === null) {
            return $visible;
        }

        return array_values(array_filter(
            $visible,
            fn (AlertKind $kind): bool => AlertRegistry::get($kind)->section === $section,
        ));
    }

    /**
     * @param  list<AlertKind>  $kinds
     * @return Builder<Alert>
     */
    private static function base(array $kinds): Builder
    {
        $query = Alert::query();

        if ($kinds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('kind', array_map(fn (AlertKind $kind): string => $kind->value, $kinds));
    }

    /**
     * @param  Builder<Alert>  $query
     */
    private static function applyState(Builder $query, ?string $state): void
    {
        if ($state === 'open') {
            $query->whereNull('resolved_at')->whereNull('acknowledged_at');
        } elseif ($state === 'acknowledged') {
            $query->whereNull('resolved_at')->whereNotNull('acknowledged_at');
        } elseif ($state === 'resolved') {
            $query->whereNotNull('resolved_at');
        }
    }
}

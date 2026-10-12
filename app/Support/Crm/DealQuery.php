<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DealQuery
{
    /**
     * The board and the paginated list share this query so the two views
     * cannot disagree about which deals match a filter.
     *
     * @param  array{owner?: string|null, type?: string|null, q?: string|null, stage?: string|null}  $filters
     */
    public static function filtered(User $actor, array $filters): Builder
    {
        $query = DB::table('deals')
            ->join('contacts', 'contacts.id', '=', 'deals.contact_id')
            ->leftJoin('users', 'users.id', '=', 'deals.owner_id')
            ->select([
                'deals.id',
                'deals.title',
                'deals.type',
                'deals.owner_id',
                'deals.contact_id',
                'deals.booking_id',
                'deals.group_id',
                'deals.stage_entered_at',
                'contacts.name as contact_name',
                'users.name as owner_name',
            ])
            ->selectRaw('('.DealStages::stageSql().') as projected_stage')
            ->selectRaw('('.DealStages::valueSql().') as value_amount')
            ->selectRaw('('.DealStages::valueLabelSql().') as value_label')
            ->selectRaw(DealStages::bookingColumnSql('COALESCE(bookings.reference, bookings.request_reference)').' as booking_reference')
            ->selectRaw(DealStages::bookingColumnSql('bookings.status').' as booking_status')
            ->selectRaw(DealStages::bookingColumnSql('departures.date').' as departure_date');

        $owner = $filters['owner'] ?? null;

        if ($owner === 'me') {
            $query->where('deals.owner_id', $actor->id);
        } elseif ($owner === 'unassigned') {
            $query->whereNull('deals.owner_id');
        } elseif (is_string($owner) && ctype_digit($owner)) {
            $query->where('deals.owner_id', (int) $owner);
        }

        $type = $filters['type'] ?? null;

        if (is_string($type) && DealType::tryFrom($type) instanceof DealType) {
            $query->where('deals.type', $type);
        }

        $stage = $filters['stage'] ?? null;

        if (is_string($stage) && DealStage::tryFrom($stage) instanceof DealStage) {
            $query->whereRaw('('.DealStages::stageSql().') = ?', [$stage]);
        }

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('deals.title', 'like', $like)
                    ->orWhere('contacts.name', 'like', $like);
            });
        }

        return $query;
    }
}

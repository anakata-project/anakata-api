<?php

declare(strict_types=1);

namespace App\Support\Inventory;

use App\Enums\ClaimKind;
use App\Models\CabinClaim;
use App\Models\Departure;
use Illuminate\Support\Collection;

final class DepartureLocks
{
    public const HISTORY_DELETE = 'This departure has inventory history (released blocks or holds). Close or hide it instead.';

    /**
     * @param  Collection<int, CabinClaim>  $claims
     */
    public static function dateAndYachtCount(Collection $claims): int
    {
        return $claims
            ->filter(fn (CabinClaim $claim): bool => $claim->released_at === null)
            ->filter(fn (CabinClaim $claim): bool => self::locksDateAndYacht($claim))
            ->count();
    }

    /**
     * @param  Collection<int, CabinClaim>  $claims
     * @return array{date_and_yacht: bool, delete: bool, reason: string|null}
     */
    public static function for(Collection $claims): array
    {
        $dateAndYacht = self::dateAndYachtCount($claims);
        $active = $claims->filter(fn (CabinClaim $claim): bool => $claim->released_at === null);
        $hasAny = $claims->isNotEmpty();

        if ($active->isNotEmpty()) {
            return [
                'date_and_yacht' => $dateAndYacht > 0,
                'delete' => true,
                'reason' => self::activeDeleteMessage($active),
            ];
        }

        if ($hasAny) {
            return [
                'date_and_yacht' => false,
                'delete' => true,
                'reason' => self::HISTORY_DELETE,
            ];
        }

        return [
            'date_and_yacht' => false,
            'delete' => false,
            'reason' => null,
        ];
    }

    public static function dateAndYachtMessage(int $count): string
    {
        return 'Date and yacht are locked — '.$count.' cabin(s) sold or held on this departure. Move guests with "Move to another departure" on each booking first.';
    }

    /**
     * @param  Collection<int, CabinClaim>  $active
     */
    public static function activeDeleteMessage(Collection $active): string
    {
        $blocked = $active->filter(fn (CabinClaim $claim): bool => $claim->kind === ClaimKind::Block)->count();
        $held = $active->filter(fn (CabinClaim $claim): bool => $claim->kind === ClaimKind::Hold)->count();
        $sold = $active->filter(fn (CabinClaim $claim): bool => $claim->kind === ClaimKind::Booking)->count();

        $parts = [];

        if ($blocked > 0) {
            $parts[] = $blocked.' blocked';
        }

        if ($held > 0) {
            $parts[] = $held.' held';
        }

        if ($sold > 0) {
            $parts[] = $sold.' sold';
        }

        return implode(', ', $parts);
    }

    /**
     * @return Collection<int, CabinClaim>
     */
    public static function claimsFor(Departure $departure): Collection
    {
        return CabinClaim::query()
            ->where('departure_id', $departure->id)
            ->get();
    }

    private static function locksDateAndYacht(CabinClaim $claim): bool
    {
        if ($claim->kind === ClaimKind::Booking) {
            return true;
        }

        if ($claim->kind !== ClaimKind::Hold) {
            return false;
        }

        return $claim->expires_at === null || $claim->expires_at->isFuture();
    }
}

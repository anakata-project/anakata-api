<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\ClaimKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Rms\HoldResource;
use App\Models\Booking;
use App\Models\CabinClaim;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class HoldController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $claims = CabinClaim::query()
            ->where('kind', ClaimKind::Hold)
            ->whereNull('released_at')
            ->with([
                'cabin',
                'departure.yacht',
                'holder' => function (Relation $morph): void {
                    if ($morph instanceof MorphTo) {
                        $morph->morphWith([
                            Booking::class => ['contact', 'cabin', 'departure.yacht', 'bookingRequest'],
                        ]);
                    }
                },
            ])
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CabinClaim $claim): string => $claim->holder_type.'|'.$claim->holder_id);

        $rows = [];

        foreach ($claims as $group) {
            $first = $group->first();
            $holder = $first?->holder;

            if (! $holder instanceof Booking) {
                continue;
            }

            $rows[] = HoldResource::fromBooking($holder);
        }

        usort($rows, function (array $a, array $b): int {
            $left = $a['expires_at']?->getTimestamp() ?? 0;
            $right = $b['expires_at']?->getTimestamp() ?? 0;

            return $left <=> $right;
        });

        return HoldResource::collection($rows);
    }
}

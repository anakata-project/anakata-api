<?php

declare(strict_types=1);

namespace App\Actions\Departures;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Departure;
use App\Support\History\History;
use App\Support\Inventory\DepartureLocks;

final class DeleteDeparture extends Action
{
    public function handle(Departure $departure): void
    {
        $this->transaction(function () use ($departure): void {
            $locks = DepartureLocks::for(DepartureLocks::claimsFor($departure));

            if ($locks['delete']) {
                throw new ConflictException((string) $locks['reason']);
            }

            History::record($departure, 'departure.deleted');
            $departure->delete();
        });
    }
}

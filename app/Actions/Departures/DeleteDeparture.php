<?php

declare(strict_types=1);

namespace App\Actions\Departures;

use App\Actions\Action;
use App\Models\Departure;
use App\Support\History\History;

final class DeleteDeparture extends Action
{
    public function handle(Departure $departure): void
    {
        $this->transaction(function () use ($departure): void {
            History::record($departure, 'departure.deleted');
            $departure->delete();
        });
    }
}

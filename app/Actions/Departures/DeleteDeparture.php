<?php

declare(strict_types=1);

namespace App\Actions\Departures;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Departure;
use App\Support\History\History;
use App\Support\Inventory\DepartureLocks;
use Illuminate\Database\QueryException;

final class DeleteDeparture extends Action
{
    public function handle(Departure $departure): void
    {
        $this->transaction(function () use ($departure): void {
            $departure = DepartureLocks::lock((int) $departure->id);
            $locks = DepartureLocks::for(DepartureLocks::claimsFor($departure));

            if ($locks['delete']) {
                throw new ConflictException((string) $locks['reason']);
            }

            History::record($departure, 'departure.deleted');

            try {
                $departure->delete();
            } catch (\Throwable $exception) {
                if ($exception instanceof QueryException && (int) ($exception->errorInfo[1] ?? 0) === 1451) {
                    throw new ConflictException(DepartureLocks::HISTORY_DELETE);
                }

                throw $exception;
            }
        });
    }
}

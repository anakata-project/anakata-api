<?php

declare(strict_types=1);

namespace App\Actions\Departures;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Departure;
use App\Models\Yacht;
use App\Support\Departures\YachtDateConflict;
use App\Support\History\History;
use App\Support\Inventory\DepartureLocks;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class UpdateDeparture extends Action
{
    /** @var list<string> */
    private const HISTORY_EXCLUDED = [
        'updated_at',
        'updated_by',
        'created_at',
        'created_by',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Departure $departure, array $data): Departure
    {
        $yachtId = (int) ($data['yacht_id'] ?? $departure->yacht_id);
        $date = array_key_exists('date', $data)
            ? CreateDeparture::calendarDate($data['date'])
            : $departure->date;

        if ($date->dayOfWeek !== CarbonInterface::SUNDAY) {
            throw ValidationException::withMessages([
                'date' => [YachtDateConflict::sundayMessage($date)],
            ]);
        }

        $yacht = Yacht::query()->findOrFail($yachtId);

        $dateChanging = array_key_exists('date', $data)
            && $date->toDateString() !== $departure->date->toDateString();
        $yachtChanging = array_key_exists('yacht_id', $data)
            && $yachtId !== $departure->yacht_id;

        if ($dateChanging || $yachtChanging) {
            $locked = DepartureLocks::dateAndYachtCount(DepartureLocks::claimsFor($departure));

            if ($locked > 0) {
                throw new ConflictException(DepartureLocks::dateAndYachtMessage($locked));
            }
        }

        return YachtDateConflict::guard($yacht, $date, function () use ($departure, $data): Departure {
            return $this->transaction(function () use ($departure, $data): Departure {
                foreach ($data as $key => $value) {
                    $departure->setAttribute($key, $value);
                }

                if (! $departure->isDirty()) {
                    return $departure;
                }

                $departure->save();

                $changes = $departure->getChanges();
                $previous = $departure->getPrevious();

                $contentBefore = [];
                $contentAfter = [];

                foreach ($changes as $key => $value) {
                    if ($key === 'status' || in_array($key, self::HISTORY_EXCLUDED, true)) {
                        continue;
                    }

                    $contentBefore[$key] = $previous[$key] ?? null;
                    $contentAfter[$key] = $value;
                }

                if ($contentBefore !== []) {
                    History::record($departure, 'departure.updated', $contentBefore, $contentAfter);
                }

                if (array_key_exists('status', $changes)) {
                    History::record(
                        $departure,
                        'departure.status_changed',
                        ['status' => $previous['status'] ?? null],
                        ['status' => $changes['status']],
                    );
                }

                return $departure;
            });
        });
    }
}

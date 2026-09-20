<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms\Concerns;

use App\Actions\Departures\CreateDeparture;
use App\Models\Departure;
use App\Support\Departures\YachtDateConflict;
use Carbon\CarbonInterface;
use Illuminate\Validation\Validator;

trait ValidatesDepartureDate
{
    protected function validateSundayAndUniqueness(
        Validator $validator,
        ?int $ignoreId = null,
        mixed $dateInput = null,
        mixed $yachtId = null,
    ): void {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $dateInput ??= $this->input('date');
        $yachtId ??= $this->input('yacht_id');

        if (! is_string($dateInput) || $dateInput === '') {
            return;
        }

        $date = CreateDeparture::calendarDate($dateInput);

        if ($date->dayOfWeek !== CarbonInterface::SUNDAY) {
            $validator->errors()->add('date', YachtDateConflict::sundayMessage($date));

            return;
        }

        if (! is_numeric($yachtId)) {
            return;
        }

        $existing = Departure::query()
            ->with('yacht')
            ->where('yacht_id', (int) $yachtId)
            ->whereDate('date', $date->toDateString())
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first();

        if ($existing instanceof Departure) {
            $validator->errors()->add(
                'date',
                YachtDateConflict::duplicateMessage($existing->yacht->code, $date, $existing->reference),
            );
        }
    }
}

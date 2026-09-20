<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\BookingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuoteReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'departure_id' => ['required', 'integer', 'exists:departures,id'],
            'type' => ['required', Rule::enum(BookingType::class)],
            'back_to_back' => ['sometimes', 'boolean'],
            'cabins' => ['required', 'array', 'min:1'],
            'cabins.*.cabin_code' => ['nullable', 'string', 'max:16'],
            'cabins.*.adults' => ['required', 'integer', 'min:0', 'max:36'],
            'cabins.*.children' => ['required', 'integer', 'min:0', 'max:36'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $after): void {
            $type = $this->input('type');
            $cabins = $this->input('cabins');

            if (! is_array($cabins)) {
                return;
            }

            if ($type === BookingType::Charter->value) {
                if (count($cabins) !== 1) {
                    $after->errors()->add('cabins', 'A charter has one party and no cabin code.');
                }

                if (isset($cabins[0]) && is_array($cabins[0]) && filled($cabins[0]['cabin_code'] ?? null)) {
                    $after->errors()->add('cabins.0.cabin_code', 'A charter has no cabin code.');
                }

                return;
            }

            foreach ($cabins as $index => $row) {
                if (! is_array($row) || blank($row['cabin_code'] ?? null)) {
                    $after->errors()->add('cabins.'.$index.'.cabin_code', 'Pick a cabin.');
                }
            }
        });
    }
}

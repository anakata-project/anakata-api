<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;

final class PaymentOptions
{
    /**
     * @return array{
     *     kinds: list<array{value: string, label: string, recordable: bool}>,
     *     methods: list<array{value: string, label: string, recordable: bool}>
     * }
     */
    public static function all(): array
    {
        return [
            'kinds' => array_map(
                fn (PaymentKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                    'recordable' => $kind->recordable(),
                ],
                PaymentKind::cases(),
            ),
            'methods' => array_map(
                fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'recordable' => $method->recordable(),
                ],
                PaymentMethod::cases(),
            ),
        ];
    }
}

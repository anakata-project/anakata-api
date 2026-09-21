<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class DocumentsRules
{
    public function __construct(
        public int $pretripDaysBefore,
        public int $voucherDaysBefore,
    ) {}

    /**
     * @return array{pretrip_days_before: int, voucher_days_before: int}
     */
    public function toArray(): array
    {
        return [
            'pretrip_days_before' => $this->pretripDaysBefore,
            'voucher_days_before' => $this->voucherDaysBefore,
        ];
    }
}

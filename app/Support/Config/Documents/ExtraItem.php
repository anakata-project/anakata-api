<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class ExtraItem
{
    public function __construct(
        public string $code,
        public string $name,
        public string $unit,
        public ?int $priceUsd,
        public bool $triggersTransferVoucher,
        public bool $active,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $price = $data['price_usd'] ?? null;

        return new self(
            (string) ($data['code'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['unit'] ?? ''),
            $price === null || $price === '' ? null : (int) $price,
            (bool) ($data['triggers_transfer_voucher'] ?? false),
            (bool) ($data['active'] ?? false),
        );
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     unit: string,
     *     price_usd: int|null,
     *     triggers_transfer_voucher: bool,
     *     active: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'price_usd' => $this->priceUsd,
            'triggers_transfer_voucher' => $this->triggersTransferVoucher,
            'active' => $this->active,
        ];
    }

    public function isOnRequest(): bool
    {
        return $this->priceUsd === null;
    }
}

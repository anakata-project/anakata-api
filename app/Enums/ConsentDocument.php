<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentDocument: string
{
    case Terms = 'TERMS';
    case Cancellation = 'CANCELLATION';
    case Privacy = 'PRIVACY';
    case Insurance = 'INSURANCE';
    case Marketing = 'MARKETING';

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Terms & Conditions',
            self::Cancellation => 'Cancellation policy',
            self::Privacy => 'Privacy policy',
            self::Insurance => 'Travel insurance declaration',
            self::Marketing => 'Marketing (optional)',
        };
    }

    public function versionKey(): string
    {
        return match ($this) {
            self::Terms => 'terms',
            self::Cancellation => 'cancellation',
            self::Privacy => 'privacy',
            self::Insurance => 'insurance',
            self::Marketing => 'marketing',
        };
    }

    public function required(): bool
    {
        return $this !== self::Marketing;
    }
}

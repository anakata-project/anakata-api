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
    case CharterProposal = 'CHARTER_PROPOSAL';

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Terms & Conditions',
            self::Cancellation => 'Cancellation policy',
            self::Privacy => 'Privacy policy',
            self::Insurance => 'Travel insurance declaration',
            self::Marketing => 'Marketing (optional)',
            self::CharterProposal => 'Charter proposal',
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
            self::CharterProposal => 'charter_proposal',
        };
    }

    public function required(): bool
    {
        return $this !== self::Marketing && $this !== self::CharterProposal;
    }

    /**
     * The declarations a guest ticks on a booking. A charter proposal acceptance
     * is recorded on its own booking and is not one of these.
     *
     * @return list<self>
     */
    public static function checklist(): array
    {
        return array_filter(
            self::cases(),
            fn (self $document): bool => $document !== self::CharterProposal,
        );
    }
}

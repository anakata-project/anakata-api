<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactType;
use App\Enums\PreferredChannel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Services\Config\CurrentConfig;
use App\Support\Crm\ContactDerived;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country
 * @property PreferredChannel $preferred_channel
 * @property ContactType $type
 * @property string $language
 * @property string|null $phone_e164
 * @property array<string, mixed>|null $first_touch
 * @property array<string, mixed>|null $last_touch
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Booking> $bookings
 */
#[Fillable([
    'name',
    'email',
    'phone',
    'country',
    'preferred_channel',
    'type',
    'language',
    'phone_e164',
    'first_touch',
    'last_touch',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_channel' => PreferredChannel::class,
            'type' => ContactType::class,
            'first_touch' => 'array',
            'last_touch' => 'array',
        ];
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = self::normalizeEmail($value);
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopeWithDerived(Builder $query): void
    {
        $crm = app(CurrentConfig::class)->businessRules()->crm;

        if ($query->getQuery()->columns === null) {
            $query->select('contacts.*');
        }

        $query
            ->selectRaw('('.ContactDerived::lifetimeValueSql().') as lifetime_value')
            ->selectRaw('('.ContactDerived::segmentSql($crm).') as segment')
            ->selectRaw('('.ContactDerived::lifecycleSql().') as lifecycle')
            ->selectRaw('('.ContactDerived::marketingConsentSql().') as marketing_consent')
            ->selectRaw('('.ContactDerived::firstBookingColumnSql('main_channel').') as first_main_channel')
            ->selectRaw('('.ContactDerived::firstBookingColumnSql('channel_of_origin').') as first_channel_of_origin');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function historyLabel(): string
    {
        return $this->name;
    }
}

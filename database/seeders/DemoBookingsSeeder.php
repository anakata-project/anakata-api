<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Contacts\ResolveContact;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\ClaimKind;
use App\Enums\ConfigKind;
use App\Enums\PreferredChannel;
use App\Enums\ReferenceType;
use App\Models\Booking;
use App\Models\Cabin;
use App\Models\Departure;
use App\Models\Group;
use App\Models\User;
use App\Models\Yacht;
use App\Services\Config\CurrentConfig;
use App\Services\Inventory\ClaimService;
use App\Services\Pricing\CabinPricer;
use App\Services\Pricing\NoRate;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteInput;
use App\Services\References\ReferenceService;
use App\Support\Bookings\ChannelSeedMap;
use App\Support\History\History;
use App\Support\Inventory\DepartureLocks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class DemoBookingsSeeder extends Seeder
{
    /**
     * @var list<array{reference: string, seed_total: int, calculator_total: int}>
     */
    public static array $priceDifferences = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        self::$priceDifferences = [];

        $seed = $this->seedFile();
        $departures = $this->anamaraByDateIndex($seed['departures'] ?? []);
        $groupRow = $seed['groups'][0] ?? null;
        $bookings = array_values(array_filter(
            $seed['bookings'] ?? [],
            fn (array $row): bool => ($row['st'] ?? '') !== 'REQUESTED',
        ));

        $departureIds = [];

        foreach ($bookings as $row) {
            $departureIds[] = $this->departureFor((int) $row['dep'], $departures)->id;
        }

        DB::transaction(function () use ($bookings, $departures, $groupRow, $departureIds): void {
            DepartureLocks::lockMany($departureIds);

            $group = is_array($groupRow) ? $this->seedGroup($groupRow, $departures) : null;

            foreach ($bookings as $row) {
                $this->seedBooking($row, $departures, $group);
            }

            app(ReferenceService::class)->ensureAtLeast(ReferenceType::Group, 7);
            app(ReferenceService::class)->ensureAtLeast(ReferenceType::Booking, 19, 2026);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, Departure>  $departures
     */
    private function seedGroup(array $row, array $departures): Group
    {
        $coord = is_array($row['coord'] ?? null) ? $row['coord'] : [];
        $contact = app(ResolveContact::class)->handle([
            'name' => (string) ($coord['name'] ?? 'Coordinator'),
            'email' => isset($coord['email']) ? (string) $coord['email'] : null,
            'phone' => isset($coord['phone']) ? (string) $coord['phone'] : null,
            'preferred_channel' => $this->preferredChannel($coord['pc'] ?? null),
        ]);

        $departure = $this->departureFor(3, $departures);

        $group = Group::query()->firstOrCreate(
            ['reference' => (string) $row['id']],
            [
                'name' => (string) $row['name'],
                'departure_id' => $departure->id,
                'coordinator_contact_id' => $contact->id,
            ],
        );

        if ($group->wasRecentlyCreated) {
            History::record($group, 'group.created', after: [
                'reference' => $group->reference,
                'name' => $group->name,
            ]);
        }

        return $group;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, Departure>  $departures
     */
    private function seedBooking(array $row, array $departures, ?Group $group): void
    {
        $departure = $this->departureFor((int) $row['dep'], $departures);
        $type = BookingType::from((string) $row['type']);
        $status = $this->status((string) $row['st']);
        $channels = ChannelSeedMap::fromPrototype((string) $row['chan']);
        $quote = $this->price($departure, $type, $row);
        $seedTotal = (int) $row['total'];

        if ($quote->total !== $seedTotal) {
            self::$priceDifferences[] = [
                'reference' => (string) $row['id'],
                'seed_total' => $seedTotal,
                'calculator_total' => $quote->total,
            ];
        }

        $contact = app(ResolveContact::class)->handle([
            'name' => (string) $row['guest'],
            'email' => $this->leadEmail($row),
        ]);

        $cabin = $this->cabin($departure, $row);
        $terms = app(CurrentConfig::class)->rates()->terms;

        $booking = Booking::query()->firstOrCreate(
            ['reference' => (string) $row['id']],
            [
                'request_reference' => null,
                'type' => $type,
                'departure_id' => $departure->id,
                'cabin_id' => $cabin?->id,
                'contact_id' => $contact->id,
                'group_id' => ($row['grp'] ?? null) === 'GRP-007' ? $group?->id : null,
                'owner_id' => $this->owner((string) $row['owner'])->id,
                'status' => $status,
                'main_channel' => $channels['main'],
                'channel_of_origin' => $channels['origin'],
                'adults' => (int) ($row['adults'] ?? 0),
                'children' => (int) ($row['children'] ?? 0),
                'back_to_back' => false,
                'rates_version_id' => app(CurrentConfig::class)->version(ConfigKind::Rates)->id,
                'price_lines' => $quote->toArray()['lines'],
                'total' => $quote->total,
                'deposit_pct' => $quote->depositPct,
                'balance_days' => $type === BookingType::Charter
                    ? $terms->charterBalanceDays
                    : $terms->cabinBalanceDays,
            ],
        );

        if ($booking->wasRecentlyCreated) {
            History::record($booking, 'booking.created', after: [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'total' => $booking->total,
                'what' => 'Reservation created in RMS — '.$booking->cabinLabel().' · '.$booking->partyLabel().' · seeded',
            ]);
        }

        if (! $status->holdsInventory()) {
            return;
        }

        if ($booking->claims()->whereNull('released_at')->exists()) {
            return;
        }

        $cabins = $type === BookingType::Charter
            ? $departure->yacht->cabins->sortBy('sort')->values()
            : collect([$cabin]);

        app(ClaimService::class)->claim($departure, $cabins, $booking, ClaimKind::Booking);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function price(Departure $departure, BookingType $type, array $row): Quote
    {
        $cabin = $this->cabin($departure, $row);
        $input = new QuoteInput(
            year: (int) $departure->date->format('Y'),
            type: $type->quoteType(),
            category: $cabin?->category,
            adults: (int) ($row['adults'] ?? 0),
            children: (int) ($row['children'] ?? 0),
            festive: $departure->festive,
        );

        $priced = app(CabinPricer::class)->quote(app(CurrentConfig::class)->rates(), $input);

        if ($priced instanceof NoRate) {
            throw new RuntimeException($priced->reason.' for '.(string) $row['id']);
        }

        return $priced;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function cabin(Departure $departure, array $row): ?Cabin
    {
        if (($row['type'] ?? '') === 'CHARTER' || ($row['cab'] ?? '') === 'ALL') {
            return null;
        }

        $cabin = $departure->yacht->cabins->first(
            fn (Cabin $item): bool => $item->code === (string) $row['cab'],
        );

        if (! $cabin instanceof Cabin) {
            throw new RuntimeException('Cabin '.$row['cab'].' is missing on '.$departure->reference.'.');
        }

        return $cabin;
    }

    /**
     * @param  array<int, Departure>  $departures
     */
    private function departureFor(int $di, array $departures): Departure
    {
        $departure = $departures[$di] ?? null;

        if (! $departure instanceof Departure) {
            throw new RuntimeException('No ANAMARA departure for date index '.$di.'.');
        }

        return $departure;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, Departure>
     */
    private function anamaraByDateIndex(array $rows): array
    {
        $yacht = Yacht::query()->where('code', 'ANAMARA')->firstOrFail();
        $mapped = [];

        foreach ($rows as $row) {
            if (($row['yacht'] ?? '') !== 'ANAMARA') {
                continue;
            }

            $departure = Departure::query()
                ->where('yacht_id', $yacht->id)
                ->whereDate('date', (string) $row['date'])
                ->with('yacht.cabins')
                ->first();

            if (! $departure instanceof Departure) {
                throw new RuntimeException('ANAMARA departure '.$row['date'].' is not seeded.');
            }

            $mapped[(int) $row['di']] = $departure;
        }

        return $mapped;
    }

    private function status(string $status): BookingStatus
    {
        if ($status === 'OVERDUE') {
            return BookingStatus::Confirmed;
        }

        return BookingStatus::from($status);
    }

    private function owner(string $firstName): User
    {
        $user = User::query()
            ->get()
            ->first(fn (User $candidate): bool => str_starts_with($candidate->name, $firstName));

        if (! $user instanceof User) {
            throw new RuntimeException('No demo user named '.$firstName.'.');
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function leadEmail(array $row): ?string
    {
        $guests = $row['guests'] ?? [];

        if (! is_array($guests)) {
            return null;
        }

        foreach ($guests as $guest) {
            if (! is_array($guest)) {
                continue;
            }

            $email = $guest['email'] ?? '';

            if (($guest['lead'] ?? false) && is_string($email) && $email !== '') {
                return $email;
            }
        }

        foreach ($guests as $guest) {
            if (! is_array($guest)) {
                continue;
            }

            $email = $guest['email'] ?? '';

            if (is_string($email) && $email !== '') {
                return $email;
            }
        }

        return null;
    }

    private function preferredChannel(mixed $value): PreferredChannel
    {
        if (is_string($value) && $value !== '') {
            return PreferredChannel::from($value);
        }

        return PreferredChannel::Email;
    }

    /**
     * @return array{departures: list<array<string, mixed>>, groups: list<array<string, mixed>>, bookings: list<array<string, mixed>>}
     */
    private function seedFile(): array
    {
        $path = base_path('docs/requirements/examples/seed-data.json');

        try {
            /** @var array{departures: list<array<string, mixed>>, groups: list<array<string, mixed>>, bookings: list<array<string, mixed>>} $seed */
            $seed = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('seed-data.json is not valid JSON.', 0, $exception);
        }

        return $seed;
    }
}

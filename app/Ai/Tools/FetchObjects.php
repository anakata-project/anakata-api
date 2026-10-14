<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\Permission;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CharterEnquiry;
use App\Models\Contact;
use App\Models\CrmTask;
use App\Models\Deal;
use App\Models\Departure;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Inventory\Availability;
use App\Support\SensitiveFields;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One read tool. Each type is gated by the same policy the panel uses.
 */
class FetchObjects implements Tool
{
    use ReturnsToolJson;

    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'contact' => Contact::class,
        'deal' => Deal::class,
        'task' => CrmTask::class,
        'booking' => Booking::class,
        'departure' => Departure::class,
        'offer' => Offer::class,
        'payment' => Payment::class,
        'charter_enquiry' => CharterEnquiry::class,
        'agency' => Agency::class,
    ];

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Search staff records: contacts, deals, tasks, bookings, departures, offers, payments, '
            .'charter enquiries, and agencies. Results respect the signed-in user\'s permissions. '
            .'Passport, medical, date of birth and nationality are never returned.';
    }

    public function handle(Request $request): Stringable|string
    {
        $objectType = (string) ($request['object_type'] ?? '');

        if (! isset(self::MODELS[$objectType])) {
            return $this->failure("Unsupported object_type '{$objectType}'.");
        }

        $modelClass = self::MODELS[$objectType];

        if (! $this->canView($objectType, $modelClass)) {
            return $this->failure("You do not have permission to view {$objectType} records.");
        }

        /** @var Builder<Model> $query */
        $query = $modelClass::query();
        $this->constrain($query, $objectType);
        $this->applyFilters($query, $objectType, $request);

        $limit = min(max((int) ($request['limit'] ?? 10), 1), 30);
        $records = $query->latest()->limit($limit)->get();

        if ($objectType === 'departure') {
            $shaped = $this->shapeDepartures($records);
        } else {
            $shaped = $records->map(fn (Model $record): array => $this->shape($record))->all();
        }

        $payload = SensitiveFields::strip([
            'success' => true,
            'object_type' => $objectType,
            'total' => count($shaped),
            'results' => $shaped,
        ]);

        return $this->encodePayload(is_array($payload) ? $payload : []);
    }

    /**
     * Payments have no viewAny ability. Staff who can open the RMS may read a summary.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function canView(string $objectType, string $modelClass): bool
    {
        if ($objectType === 'payment') {
            return $this->user->hasPermission(Permission::PanelRms);
        }

        return Gate::forUser($this->user)->allows('viewAny', $modelClass);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function constrain(Builder $query, string $objectType): void
    {
        if ($objectType === 'contact') {
            /** @var Builder<Contact> $query */
            $query->notMerged();
        }

        if ($objectType === 'deal' || $objectType === 'booking' || $objectType === 'charter_enquiry') {
            $query->with('contact');
        }

        if ($objectType === 'payment') {
            $query->with('booking');
        }

        if ($objectType === 'booking') {
            /** @var Builder<Booking> $query */
            $query->visibleTo($this->user);
        }

        if ($objectType === 'payment' && ! $this->user->hasPermission(Permission::BookingsViewAll)) {
            $query->whereHas('booking', function (Builder $booking): void {
                /** @var Builder<Booking> $booking */
                $booking->visibleTo($this->user);
            });
        }

        if ($objectType === 'task' && ! $this->user->hasPermission(Permission::RecordsActOnAny)) {
            $query->where('owner_id', $this->user->id);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyFilters(Builder $query, string $objectType, Request $request): void
    {
        $search = isset($request['search']) ? trim((string) $request['search']) : '';
        $contactId = $request['contact_id'] ?? null;
        $dealId = $request['deal_id'] ?? null;
        $bookingId = $request['booking_id'] ?? null;
        $status = isset($request['status']) ? (string) $request['status'] : '';

        if ($search !== '') {
            $like = '%'.$search.'%';
            match ($objectType) {
                'contact' => $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);
                }),
                'deal' => $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('title', 'like', $like)
                        ->orWhereHas('contact', fn (Builder $contact) => $contact->where('name', 'like', $like));
                }),
                'task' => $query->where('title', 'like', $like),
                'booking' => $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('reference', 'like', $like)->orWhere('request_reference', 'like', $like);
                }),
                'departure' => $query->where('reference', 'like', $like),
                'offer' => $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('reference', 'like', $like);
                }),
                'agency' => $query->where('name', 'like', $like),
                'charter_enquiry' => $query->whereHas('contact', fn (Builder $contact) => $contact->where('name', 'like', $like)),
                default => null,
            };
        }

        if ($contactId) {
            match ($objectType) {
                'deal', 'task', 'booking', 'charter_enquiry' => $query->where('contact_id', $contactId),
                'payment' => $query->whereHas('booking', fn (Builder $booking) => $booking->where('contact_id', $contactId)),
                default => null,
            };
        }

        if ($dealId && $objectType === 'task') {
            $query->where('deal_id', $dealId);
        }

        if ($bookingId && in_array($objectType, ['payment', 'task'], true)) {
            $query->where('booking_id', $bookingId);
        }

        if ($status !== '' && $objectType === 'deal') {
            $query->where('stage', $status);
        } elseif ($status !== '' && in_array($objectType, ['task', 'booking', 'departure', 'offer', 'payment', 'charter_enquiry', 'agency'], true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Model $record): array
    {
        if ($record instanceof Contact) {
            return [
                'id' => $record->id,
                'name' => $record->name,
                'email' => $record->email,
                'phone' => $record->phone,
                'country' => $record->country,
                'type' => $record->type->value,
            ];
        }

        if ($record instanceof Deal) {
            return [
                'id' => $record->id,
                'title' => $record->title,
                'type' => $record->type->value,
                'stage' => $record->stage->value,
                'contact_id' => $record->contact_id,
                'contact_name' => $record->contact->name,
                'owner_id' => $record->owner_id,
                'estimate' => $record->estimate,
                'booking_id' => $record->booking_id,
            ];
        }

        if ($record instanceof CrmTask) {
            return [
                'id' => $record->id,
                'title' => $record->title,
                'status' => $record->status->value,
                'due_at' => $record->due_at->format('Y-m-d'),
                'contact_id' => $record->contact_id,
                'deal_id' => $record->deal_id,
                'owner_id' => $record->owner_id,
            ];
        }

        if ($record instanceof Booking) {
            return [
                'id' => $record->id,
                'reference' => $record->reference ?? $record->request_reference,
                'status' => $record->status->value,
                'type' => $record->type->value,
                'contact_id' => $record->contact_id,
                'contact_name' => $record->contact->name,
                'departure_id' => $record->departure_id,
                'owner_id' => $record->owner_id,
                'total' => $record->total,
            ];
        }

        if ($record instanceof Offer) {
            return [
                'id' => $record->id,
                'reference' => $record->reference,
                'code' => $record->code,
                'name' => $record->name,
                'type' => $record->type->value,
                'status' => $record->status->value,
            ];
        }

        if ($record instanceof Payment) {
            return [
                'id' => $record->id,
                'reference' => $record->reference,
                'amount' => $record->amount,
                'kind' => $record->kind->value,
                'method' => $record->method->value,
                'status' => $record->status->value,
                'booking_id' => $record->booking_id,
                'booking_reference' => $record->booking->reference,
            ];
        }

        if ($record instanceof CharterEnquiry) {
            return [
                'id' => $record->id,
                'status' => $record->status->value,
                'guests' => $record->guests,
                'contact_id' => $record->contact_id,
                'contact_name' => $record->contact->name,
                'preferred_from' => $record->preferred_from?->format('Y-m-d'),
                'preferred_to' => $record->preferred_to?->format('Y-m-d'),
            ];
        }

        if ($record instanceof Agency) {
            return [
                'id' => $record->id,
                'reference' => $record->reference,
                'name' => $record->name,
                'country' => $record->country,
                'status' => $record->status->value,
                'commission_pct' => $record->commission_pct,
            ];
        }

        return ['id' => $record->getKey()];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return list<array<string, mixed>>
     */
    private function shapeDepartures(Collection $records): array
    {
        /** @var Collection<int, Departure> $departures */
        $departures = $records;
        $snapshots = app(Availability::class)->forDepartures($departures);

        return $departures->map(function (Departure $departure) use ($snapshots): array {
            $counts = $snapshots[$departure->id]->counts ?? null;

            return [
                'id' => $departure->id,
                'reference' => $departure->reference,
                'date' => $departure->date->format('Y-m-d'),
                'status' => $departure->status->value,
                'yacht_id' => $departure->yacht_id,
                'free_cabins' => is_array($counts) ? $counts['free'] : null,
                'sold_cabins' => is_array($counts) ? $counts['sold'] : null,
                'held_cabins' => is_array($counts) ? $counts['held'] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'object_type' => $schema->string()
                ->description('The kind of record to fetch')
                ->enum(array_keys(self::MODELS))
                ->required(),
            'search' => $schema->string()->description('Name, email, title, code, or reference')->nullable(),
            'contact_id' => $schema->integer()->description('Filter deals, tasks, bookings, charter enquiries, or payments')->nullable(),
            'deal_id' => $schema->integer()->description('Filter tasks')->nullable(),
            'booking_id' => $schema->integer()->description('Filter payments or tasks')->nullable(),
            'status' => $schema->string()->description('Exact status value')->nullable(),
            'limit' => $schema->integer()->description('Maximum rows, up to 30')->nullable(),
        ];
    }
}

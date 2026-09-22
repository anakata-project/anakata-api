<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Actions\Bookings\CreateBookingRequest;
use App\Actions\Bookings\CreateReservation;
use App\Actions\Bookings\MoveBooking;
use App\Actions\Bookings\TransitionBooking;
use App\Actions\Charter\CreateCharterEnquiry;
use App\Actions\Checkout\SubmitEngineCheckout;
use App\Actions\Contacts\StitchEngineIdentity;
use App\Actions\Engine\IngestBehaviouralEvents;
use App\Actions\Extras\AddBookingExtra;
use App\Actions\Extras\RemoveBookingExtra;
use App\Actions\Extras\UpdateBookingFees;
use App\Actions\Guests\AddGuest;
use App\Actions\Guests\RemoveGuest;
use App\Actions\Guests\UpdateGuest;
use App\Actions\Payments\MarkWireReceived;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\SettleGatewayPayment;
use App\Actions\Refunds\CreateRefundRequest;
use App\Enums\BehaviouralEventName;
use App\Events\AvailabilityChanged;
use App\Events\BookingChargesChanged;
use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Events\CharterEnquiryReceived;
use App\Events\ConfigPublished;
use App\Events\HoldExpired;
use App\Events\PaymentAwaitingWire;
use App\Events\PaymentSettled;
use App\Events\RefundRequested;
use App\Listeners\BumpEngineFeedVersion;
use App\Listeners\ClearCurrentConfigCache;
use App\Listeners\ExpireWebCheckoutSession;
use App\Listeners\MarkRequestHoldExpired;
use App\Listeners\OpenDealOnBookingCreated;
use App\Listeners\OpenDealOnCharterEnquiryReceived;
use App\Listeners\RaiseTasksOnBookingCreated;
use App\Listeners\RaiseTasksOnBookingStatusChanged;
use App\Listeners\RaiseTasksOnCharterEnquiry;
use App\Listeners\RaiseTasksOnPaymentAwaitingWire;
use App\Listeners\RaiseTasksOnRefundRequested;
use App\Listeners\SendOnBookingChargesChanged;
use App\Listeners\SendOnBookingStatusChanged;
use App\Listeners\SendOnPaymentSettled;
use App\Services\Config\ConfigPublisher;
use App\Services\Inventory\ClaimService;

final class EventCatalogue
{
    public const NOTE = 'There is no event bus and no replay. RMS and CRM are one application; the CRM reads the booking tables directly (B9, L5). Failed queued jobs and FAILED deliveries are listed under Sync failures.';

    /**
     * @return list<array{
     *     name: string,
     *     family: string,
     *     producer: string,
     *     listeners: list<string>
     * }>
     */
    public static function rows(): array
    {
        return [...self::domain(), ...self::behavioural()];
    }

    /**
     * @return list<class-string>
     */
    public static function domainClasses(): array
    {
        return array_map(
            fn (array $row): string => $row['class'],
            self::domainDefinitions(),
        );
    }

    /**
     * @return list<array{
     *     name: string,
     *     family: string,
     *     producer: string,
     *     listeners: list<string>
     * }>
     */
    public static function domain(): array
    {
        return array_map(
            fn (array $row): array => [
                'name' => $row['name'],
                'family' => 'domain',
                'producer' => $row['producer'],
                'listeners' => $row['listeners'],
            ],
            self::domainDefinitions(),
        );
    }

    /**
     * @return list<array{
     *     name: string,
     *     family: string,
     *     producer: string,
     *     listeners: list<string>
     * }>
     */
    public static function behavioural(): array
    {
        return array_map(
            fn (BehaviouralEventName $name): array => [
                'name' => $name->value,
                'family' => 'behavioural',
                'producer' => $name === BehaviouralEventName::IdentityStitched
                    ? self::short(StitchEngineIdentity::class)
                    : self::short(IngestBehaviouralEvents::class),
                'listeners' => [],
            ],
            BehaviouralEventName::cases(),
        );
    }

    /**
     * @return list<array{class: class-string, name: string, producer: string, listeners: list<string>}>
     */
    private static function domainDefinitions(): array
    {
        return [
            [
                'class' => BookingCreated::class,
                'name' => 'BookingCreated',
                'producer' => implode(', ', [
                    self::short(CreateReservation::class),
                    self::short(CreateBookingRequest::class),
                    self::short(SubmitEngineCheckout::class),
                ]),
                'listeners' => [
                    self::short(OpenDealOnBookingCreated::class),
                    self::short(RaiseTasksOnBookingCreated::class),
                ],
            ],
            [
                'class' => CharterEnquiryReceived::class,
                'name' => 'CharterEnquiryReceived',
                'producer' => self::short(CreateCharterEnquiry::class),
                'listeners' => [
                    self::short(OpenDealOnCharterEnquiryReceived::class),
                    self::short(RaiseTasksOnCharterEnquiry::class),
                ],
            ],
            [
                'class' => RefundRequested::class,
                'name' => 'RefundRequested',
                'producer' => self::short(CreateRefundRequest::class),
                'listeners' => [self::short(RaiseTasksOnRefundRequested::class)],
            ],
            [
                'class' => PaymentAwaitingWire::class,
                'name' => 'PaymentAwaitingWire',
                'producer' => self::short(RecordPayment::class),
                'listeners' => [self::short(RaiseTasksOnPaymentAwaitingWire::class)],
            ],
            [
                'class' => BookingStatusChanged::class,
                'name' => 'BookingStatusChanged',
                'producer' => self::short(TransitionBooking::class),
                'listeners' => [
                    self::short(SendOnBookingStatusChanged::class),
                    self::short(RaiseTasksOnBookingStatusChanged::class),
                ],
            ],
            [
                'class' => PaymentSettled::class,
                'name' => 'PaymentSettled',
                'producer' => implode(', ', [
                    self::short(RecordPayment::class),
                    self::short(MarkWireReceived::class),
                    self::short(SettleGatewayPayment::class),
                ]),
                'listeners' => [self::short(SendOnPaymentSettled::class)],
            ],
            [
                'class' => BookingChargesChanged::class,
                'name' => 'BookingChargesChanged',
                'producer' => implode(', ', [
                    self::short(AddBookingExtra::class),
                    self::short(RemoveBookingExtra::class),
                    self::short(UpdateBookingFees::class),
                    self::short(AddGuest::class),
                    self::short(UpdateGuest::class),
                    self::short(RemoveGuest::class),
                    self::short(MoveBooking::class),
                ]),
                'listeners' => [self::short(SendOnBookingChargesChanged::class)],
            ],
            [
                'class' => AvailabilityChanged::class,
                'name' => 'AvailabilityChanged',
                'producer' => self::short(ClaimService::class),
                'listeners' => [self::short(BumpEngineFeedVersion::class)],
            ],
            [
                'class' => ConfigPublished::class,
                'name' => 'ConfigPublished',
                'producer' => self::short(ConfigPublisher::class),
                'listeners' => [
                    self::short(ClearCurrentConfigCache::class),
                    self::short(BumpEngineFeedVersion::class),
                ],
            ],
            [
                'class' => HoldExpired::class,
                'name' => 'HoldExpired',
                'producer' => self::short(ClaimService::class),
                'listeners' => [
                    self::short(MarkRequestHoldExpired::class),
                    self::short(ExpireWebCheckoutSession::class),
                ],
            ],
        ];
    }

    /**
     * @param  class-string  $class
     */
    private static function short(string $class): string
    {
        $slash = strrpos($class, '\\');

        return $slash === false ? $class : substr($class, $slash + 1);
    }
}

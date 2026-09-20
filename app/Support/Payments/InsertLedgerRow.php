<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ConflictException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\References\ReferenceService;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Draws a payment reference and inserts one ledger row. No status effects,
 * no history — task 02 wraps this with ApplyPaymentEffects and payment.recorded.
 *
 * Caller must already hold the H10 locks (departure row(s) → booking row).
 * This class does not lock again.
 */
final class InsertLedgerRow
{
    public const REFERENCE_CONFLICT = 'Could not allocate a payment reference — retry.';

    public function __construct(private readonly ReferenceService $references) {}

    /**
     * @param  array{
     *     kind: PaymentKind,
     *     method: PaymentMethod,
     *     amount: int,
     *     status: PaymentStatus,
     *     paid_at?: CarbonInterface|DateTimeInterface|string|null,
     *     gateway_id?: string|null,
     *     recorded_by?: int|null,
     *     note?: string|null
     * }  $payload
     */
    public function handle(Booking $booking, array $payload): Payment
    {
        $this->guardTransaction();

        $paidAt = $payload['paid_at'] ?? BusinessTime::now()->toDateString();
        $prefix = $booking->displayReference();

        if (! is_string($prefix) || $prefix === '') {
            throw new RuntimeException('A payment needs a booking display reference.');
        }

        $payment = $this->insert($booking, $payload, $paidAt, $prefix, retried: false);
        Ledger::forgetAggregates($booking);

        return $payment;
    }

    /**
     * @param  array{
     *     kind: PaymentKind,
     *     method: PaymentMethod,
     *     amount: int,
     *     status: PaymentStatus,
     *     paid_at?: CarbonInterface|DateTimeInterface|string|null,
     *     gateway_id?: string|null,
     *     recorded_by?: int|null,
     *     note?: string|null
     * }  $payload
     */
    private function insert(
        Booking $booking,
        array $payload,
        CarbonInterface|DateTimeInterface|string $paidAt,
        string $prefix,
        bool $retried,
    ): Payment {
        $reference = $this->references->nextPayment($prefix, $payload['kind']);

        try {
            return Payment::query()->create([
                'booking_id' => $booking->id,
                'kind' => $payload['kind'],
                'method' => $payload['method'],
                'amount' => $payload['amount'],
                'reference' => $reference,
                'gateway_id' => $payload['gateway_id'] ?? null,
                'status' => $payload['status'],
                'paid_at' => $paidAt,
                'recorded_by' => $payload['recorded_by'] ?? null,
                'note' => $payload['note'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isReferenceCollision($exception)) {
                throw $exception;
            }

            if ($retried) {
                throw new ConflictException(self::REFERENCE_CONFLICT);
            }

            return $this->insert($booking, $payload, $paidAt, $prefix, retried: true);
        }
    }

    private function isReferenceCollision(QueryException $exception): bool
    {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
            return false;
        }

        return str_contains($exception->getMessage(), 'payments_reference_unique')
            || str_contains($exception->getMessage(), "for key 'payments.reference'");
    }

    private function guardTransaction(): void
    {
        if (DB::transactionLevel() > ReferenceService::$baseTransactionLevel) {
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Ledger rows must be inserted inside a transaction.');
        }

        Log::warning('InsertLedgerRow was called outside a database transaction.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\References;

use App\Enums\PaymentKind;
use App\Enums\ReferenceType;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ReferenceService
{
    public static int $baseTransactionLevel = 0;

    /**
     * Issue the next business reference. A rollback of the caller's transaction
     * rolls the counter back too, so rollbacks cannot create gaps.
     */
    public function next(ReferenceType $type, ?CarbonInterface $at = null): string
    {
        $this->guardTransaction();

        $year = $type->isYearly() ? BusinessTime::year($at ?? now()) : null;

        return $type->format($this->increment($type->scope($year)), $year);
    }

    public function nextPayment(string $bookingRef, PaymentKind $kind): string
    {
        $this->guardTransaction();

        $value = $this->increment('payment:'.$bookingRef.':'.$kind->letter());

        return $bookingRef.'-'.$kind->letter().str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    public function ensureAtLeast(ReferenceType $type, int $value, ?int $year = null): void
    {
        $this->guardTransaction();

        $year ??= $type->isYearly() ? BusinessTime::year(now()) : null;

        $this->raiseTo($type->scope($year), $value);
    }

    /**
     * One statement, exclusive lock from the start, no lock upgrade, so no
     * deadlock between concurrent draws or first draws of a new scope.
     */
    private function increment(string $scope): int
    {
        $now = now();

        DB::statement(
            'insert into `reference_sequences` (`scope`, `last_value`, `created_at`, `updated_at`) values (?, 1, ?, ?) on duplicate key update `last_value` = `last_value` + 1, `updated_at` = ?',
            [$scope, $now, $now, $now],
        );

        return (int) DB::table('reference_sequences')->where('scope', $scope)->value('last_value');
    }

    /**
     * One statement, exclusive lock from the start, no lock upgrade, so no
     * deadlock between concurrent draws or first draws of a new scope.
     */
    private function raiseTo(string $scope, int $value): void
    {
        $now = now();

        DB::statement(
            'insert into `reference_sequences` (`scope`, `last_value`, `created_at`, `updated_at`) values (?, ?, ?, ?) as new on duplicate key update `last_value` = greatest(`reference_sequences`.`last_value`, `new`.`last_value`), `updated_at` = ?',
            [$scope, $value, $now, $now, $now],
        );
    }

    private function guardTransaction(): void
    {
        if (DB::transactionLevel() > self::$baseTransactionLevel) {
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            throw new RuntimeException('References must be drawn inside a transaction.');
        }

        Log::warning('ReferenceService was called outside a database transaction.');
    }
}

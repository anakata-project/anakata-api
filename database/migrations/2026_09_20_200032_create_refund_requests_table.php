<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->timestamp('cancelled_at');
            $table->smallInteger('days_before_departure');
            $table->unsignedSmallInteger('band_min_days');
            $table->unsignedTinyInteger('penalty_pct');
            $table->unsignedInteger('penalty_amount');
            $table->unsignedInteger('paid_at_cancellation');
            $table->unsignedInteger('refund_due');
            $table->string('status');
            $table->timestamp('due_by');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->foreignId('executed_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('pending_booking_id')
                ->nullable()
                ->storedAs("if(`status` = 'PENDING', `booking_id`, null)")
                ->unique();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};

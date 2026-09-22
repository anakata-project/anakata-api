<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('severity', 16);
            $table->string('title');
            $table->text('sentence');
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('departure_id')->nullable()->constrained('departures')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->unsignedBigInteger('guest_response_id')->nullable();
            $table->foreignId('crm_task_id')->nullable()->constrained('crm_tasks')->nullOnDelete();
            $table->string('base_key');
            $table->string('idempotency_key')->unique();
            $table->timestamp('raised_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index('base_key');
            $table->index(['resolved_at', 'acknowledged_at', 'severity']);
        });

        Schema::create('alert_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notifications');
        Schema::dropIfExists('alerts');
    }
};

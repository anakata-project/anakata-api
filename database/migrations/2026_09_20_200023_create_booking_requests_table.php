<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('preferred_channel');
            $table->boolean('travel_advisor')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('sla_due_at');
            $table->string('hold_rule');
            $table->timestamp('hold_expired_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};

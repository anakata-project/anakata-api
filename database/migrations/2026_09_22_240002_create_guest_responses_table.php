<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('recommend')->nullable();
            $table->string('why', 1000)->nullable();
            $table->string('best', 1000)->nullable();
            $table->string('better', 1000)->nullable();
            $table->string('crew', 1000)->nullable();
            $table->string('call_notes', 1000)->nullable();
            $table->string('source', 16);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at');
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['guest_id', 'booking_id']);
            $table->index('responded_at');
        });

        Schema::table('alerts', function (Blueprint $table): void {
            $table->foreign('guest_response_id')->references('id')->on('guest_responses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropForeign(['guest_response_id']);
        });

        Schema::dropIfExists('guest_responses');
    }
};

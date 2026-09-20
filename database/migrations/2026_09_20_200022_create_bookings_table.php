<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('request_reference')->nullable()->unique();
            $table->string('type');
            $table->foreignId('departure_id')->constrained()->restrictOnDelete();
            $table->foreignId('cabin_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->string('main_channel');
            $table->string('channel_of_origin');
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children');
            $table->boolean('back_to_back')->default(false);
            $table->foreignId('rates_version_id')->constrained('rate_versions')->restrictOnDelete();
            $table->json('price_lines');
            $table->unsignedInteger('total');
            $table->unsignedTinyInteger('deposit_pct');
            $table->unsignedSmallInteger('balance_days');
            $table->text('internal_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['departure_id', 'status']);
            $table->index('owner_id');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('departure_id')->constrained()->restrictOnDelete();
            $table->json('cabins');
            $table->string('status');
            $table->timestamp('expires_at');
            $table->boolean('extended')->default(false);
            $table->string('ip_hash', 64);
            $table->string('path')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->timestamp('stripe_expires_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['ip_hash', 'status']);
            $table->index(['status', 'stripe_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};

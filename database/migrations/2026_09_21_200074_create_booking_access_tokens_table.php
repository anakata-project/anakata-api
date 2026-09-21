<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('page_url');
            $table->timestamps();
            $table->auditColumns();

            $table->index(['booking_id', 'purpose', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_access_tokens');
    }
};

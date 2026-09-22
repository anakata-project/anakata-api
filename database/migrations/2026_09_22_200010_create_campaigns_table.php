<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('offer_id')->nullable()->constrained('offers')->restrictOnDelete();
            $table->string('utm_campaign', 120)->nullable()->unique();
            $table->string('audience', 500)->nullable();
            $table->unsignedInteger('media_spend')->default(0);
            $table->string('status', 32);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};

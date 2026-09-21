<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('png_collected')->default(false)->after('internal_notes');
            $table->boolean('tct_collected')->default(false)->after('png_collected');
            $table->unsignedInteger('tct_rate_usd')->nullable()->after('tct_collected');
        });

        Schema::create('booking_extras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->string('unit', 64);
            $table->unsignedInteger('qty');
            $table->unsignedInteger('rate_usd');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_extras');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['png_collected', 'tct_collected', 'tct_rate_usd']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE deliveries MODIFY booking_id BIGINT UNSIGNED NULL');

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE deliveries MODIFY booking_id BIGINT UNSIGNED NOT NULL');

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });
    }
};

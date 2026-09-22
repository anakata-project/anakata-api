<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->foreignId('guest_id')->nullable()->after('booking_id')->constrained('guests')->nullOnDelete();
            $table->json('covered_guest_ids')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_id');
            $table->dropColumn('covered_guest_ids');
        });
    }
};

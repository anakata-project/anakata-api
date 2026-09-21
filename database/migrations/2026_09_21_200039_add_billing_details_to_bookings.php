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
            $table->string('billing_name')->nullable()->after('internal_notes');
            $table->text('billing_address')->nullable()->after('billing_name');
            $table->string('billing_email')->nullable()->after('billing_address');
            $table->string('billing_phone')->nullable()->after('billing_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['billing_name', 'billing_address', 'billing_email', 'billing_phone']);
        });
    }
};

<?php

declare(strict_types=1);

use App\Support\Bookings\SoldOn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('promo_code')->nullable()->after('balance_days');
            $table->boolean('online_deposit')->default(false)->after('promo_code');
            $table->date('sold_on')->nullable()->after('online_deposit');
        });

        SoldOn::backfillMissing();

        Schema::table('bookings', function (Blueprint $table): void {
            $table->date('sold_on')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['promo_code', 'online_deposit', 'sold_on']);
        });
    }
};

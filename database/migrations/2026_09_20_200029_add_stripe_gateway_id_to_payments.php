<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('stripe_gateway_id')
                ->nullable()
                ->storedAs("if(`method` in ('CARD_STRIPE', 'STRIPE_LINK'), `gateway_id`, null)")
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['stripe_gateway_id']);
            $table->dropColumn('stripe_gateway_id');
        });
    }
};

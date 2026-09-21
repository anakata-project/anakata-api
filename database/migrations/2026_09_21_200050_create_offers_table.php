<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->unsignedInteger('value')->nullable();
            $table->string('value_text')->nullable();
            $table->string('channel');
            $table->string('partner')->nullable();
            $table->json('cabin_types');
            $table->json('itinerary_codes');
            $table->date('booking_from')->nullable();
            $table->date('booking_to')->nullable();
            $table->date('travel_from')->nullable();
            $table->date('travel_to')->nullable();
            $table->boolean('combinable')->default(false);
            $table->boolean('is_promo_code')->default(false);
            $table->string('badge')->nullable();
            $table->boolean('show_on_card')->default(false);
            $table->boolean('show_on_departures')->default(false);
            $table->string('price_line')->nullable();
            $table->text('terms')->nullable();
            $table->string('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_reason')->nullable();
            $table->boolean('needs_reapproval')->default(false);
            $table->timestamp('first_live_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};

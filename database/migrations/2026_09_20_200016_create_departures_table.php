<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departures', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->date('date');
            $table->foreignId('yacht_id')->constrained('yachts')->restrictOnDelete();
            $table->foreignId('itinerary_id')->constrained('itineraries')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedTinyInteger('urgency_threshold')->default(3);
            $table->boolean('waitlist_enabled')->default(true);
            $table->string('public_note', 40)->nullable();
            $table->boolean('festive')->default(false);
            $table->timestamps();
            $table->auditColumns();
            $table->unique(['yacht_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departures');
    }
};

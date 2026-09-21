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
        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('engine_identified_at')->nullable()->after('last_touch');
        });

        Schema::create('behavioural_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 64)->index();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 64);
            $table->json('params');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['session_id', 'contact_id']);
            $table->index(['contact_id', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('behavioural_event_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('name', 64);
            $table->string('itinerary_code', 32)->default('');
            $table->unsignedInteger('count');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['date', 'name', 'itinerary_code']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('utm_first')->nullable()->after('channel_of_origin');
            $table->json('utm_last')->nullable()->after('utm_first');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER bookings_prevent_utm_update
BEFORE UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF NOT (NEW.utm_first <=> OLD.utm_first)
        OR NOT (NEW.utm_last <=> OLD.utm_last) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'booking attribution columns are immutable';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS bookings_prevent_utm_update');

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['utm_first', 'utm_last']);
        });

        Schema::dropIfExists('behavioural_event_daily');
        Schema::dropIfExists('behavioural_events');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn('engine_identified_at');
        });
    }
};

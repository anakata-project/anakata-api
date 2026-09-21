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
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('document', 32);
            $table->string('version', 120);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('source', 16);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('how_obtained')->nullable();
            $table->boolean('withdrawn')->default(false);
            $table->timestamps();
            $table->auditColumns();

            $table->index(['booking_id', 'document']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER consents_prevent_update
BEFORE UPDATE ON consents
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'consents is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER consents_prevent_delete
BEFORE DELETE ON consents
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'consents is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS consents_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS consents_prevent_delete');
        Schema::dropIfExists('consents');
    }
};

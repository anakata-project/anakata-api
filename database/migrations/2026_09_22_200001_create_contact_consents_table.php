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
        Schema::create('contact_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('purpose', 32);
            $table->boolean('granted');
            $table->string('version', 120);
            $table->timestamp('captured_at');
            $table->string('ip', 45)->nullable();
            $table->string('capture_point', 32);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('how_obtained')->nullable();
            $table->foreignId('source_consent_id')->nullable()->constrained('consents')->restrictOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['contact_id', 'purpose', 'captured_at']);
            $table->index(['contact_id', 'session_id']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER contact_consents_prevent_update
BEFORE UPDATE ON contact_consents
FOR EACH ROW
BEGIN
    IF NEW.contact_id = OLD.contact_id
        OR NOT (NEW.purpose <=> OLD.purpose)
        OR NOT (NEW.granted <=> OLD.granted)
        OR NOT (NEW.version <=> OLD.version)
        OR NOT (NEW.captured_at <=> OLD.captured_at)
        OR NOT (NEW.ip <=> OLD.ip)
        OR NOT (NEW.capture_point <=> OLD.capture_point)
        OR NOT (NEW.recorded_by <=> OLD.recorded_by)
        OR NOT (NEW.how_obtained <=> OLD.how_obtained)
        OR NOT (NEW.source_consent_id <=> OLD.source_consent_id)
        OR NOT (NEW.session_id <=> OLD.session_id)
        OR NOT (NEW.created_at <=> OLD.created_at)
        OR NOT (NEW.created_by <=> OLD.created_by)
    THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contact_consents is append-only';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER contact_consents_prevent_delete
BEFORE DELETE ON contact_consents
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contact_consents is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS contact_consents_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS contact_consents_prevent_delete');
        Schema::dropIfExists('contact_consents');
    }
};

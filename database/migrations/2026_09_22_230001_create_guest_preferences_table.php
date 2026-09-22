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
        Schema::create('guest_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('answers');
            $table->text('accessibility')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->string('source', 16);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at');
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['guest_id', 'version']);
            $table->index(['guest_id', 'purged_at']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER guest_preferences_prevent_update
BEFORE UPDATE ON guest_preferences
FOR EACH ROW
BEGIN
    IF OLD.purged_at IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'guest_preferences is already purged';
    END IF;

    IF NEW.purged_at IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'guest_preferences versions are immutable';
    END IF;

    IF NOT (NEW.guest_id <=> OLD.guest_id)
        OR NOT (NEW.version <=> OLD.version)
        OR NOT (NEW.source <=> OLD.source)
        OR NOT (NEW.recorded_by <=> OLD.recorded_by)
        OR NOT (NEW.answered_at <=> OLD.answered_at)
        OR NOT (NEW.created_at <=> OLD.created_at)
        OR NOT (NEW.created_by <=> OLD.created_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'guest_preferences columns are immutable';
    END IF;

    IF NEW.accessibility IS NOT NULL OR NEW.emergency_contact IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'purge must clear restricted answers';
    END IF;

    IF NEW.answers IS NOT NULL AND JSON_LENGTH(NEW.answers) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'purge must clear answers';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS guest_preferences_prevent_update');
        Schema::dropIfExists('guest_preferences');
    }
};

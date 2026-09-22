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
        Schema::create('manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->string('kind', 16);
            $table->unsignedInteger('version');
            $table->string('reason', 32);
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('passengers');
            $table->unsignedInteger('complete');
            $table->char('snapshot_hash', 64);
            $table->string('pdf_path', 255)->nullable();
            $table->string('csv_path', 255)->nullable();
            $table->string('xlsx_path', 255)->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['departure_id', 'kind', 'version']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER manifests_prevent_delete
BEFORE DELETE ON manifests
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifests is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER manifests_prevent_update
BEFORE UPDATE ON manifests
FOR EACH ROW
BEGIN
    IF NOT (NEW.departure_id <=> OLD.departure_id)
        OR NOT (NEW.kind <=> OLD.kind)
        OR NOT (NEW.version <=> OLD.version)
        OR NOT (NEW.reason <=> OLD.reason)
        OR NOT (NEW.generated_at <=> OLD.generated_at)
        OR NOT (NEW.generated_by <=> OLD.generated_by)
        OR NOT (NEW.passengers <=> OLD.passengers)
        OR NOT (NEW.complete <=> OLD.complete)
        OR NOT (NEW.snapshot_hash <=> OLD.snapshot_hash)
        OR NOT (NEW.created_at <=> OLD.created_at)
        OR NOT (NEW.created_by <=> OLD.created_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifests columns are immutable';
    END IF;

    IF NOT (NEW.pdf_path <=> OLD.pdf_path)
        AND NOT (NEW.pdf_path IS NULL AND OLD.pdf_path IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifest file paths can only be cleared';
    END IF;

    IF NOT (NEW.csv_path <=> OLD.csv_path)
        AND NOT (NEW.csv_path IS NULL AND OLD.csv_path IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifest file paths can only be cleared';
    END IF;

    IF NOT (NEW.xlsx_path <=> OLD.xlsx_path)
        AND NOT (NEW.xlsx_path IS NULL AND OLD.xlsx_path IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifest file paths can only be cleared';
    END IF;

    IF NOT (NEW.purged_at <=> OLD.purged_at)
        AND NOT (OLD.purged_at IS NULL AND NEW.purged_at IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'manifests purged_at can only be set once';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS manifests_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS manifests_prevent_delete');
        Schema::dropIfExists('manifests');
    }
};

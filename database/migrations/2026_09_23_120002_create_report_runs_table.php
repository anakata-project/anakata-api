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
        Schema::create('report_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('definition_key', 64);
            $table->json('parameters');
            $table->date('window_from');
            $table->date('window_to');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->unsignedInteger('rows')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->string('csv_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['definition_key', 'window_from']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER report_runs_prevent_delete
BEFORE DELETE ON report_runs
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report_runs cannot be deleted'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER report_runs_prevent_update
BEFORE UPDATE ON report_runs
FOR EACH ROW
BEGIN
    IF NOT (NEW.definition_key <=> OLD.definition_key)
        OR NOT (NEW.parameters <=> OLD.parameters)
        OR NOT (NEW.window_from <=> OLD.window_from)
        OR NOT (NEW.window_to <=> OLD.window_to)
        OR NOT (NEW.requested_by <=> OLD.requested_by)
        OR NOT (NEW.subscription_id <=> OLD.subscription_id)
        OR NOT (NEW.created_at <=> OLD.created_at)
        OR NOT (NEW.created_by <=> OLD.created_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report run identity is immutable';
    END IF;

    IF NOT (NEW.csv_path <=> OLD.csv_path)
        AND NOT (
            (OLD.csv_path IS NULL AND NEW.csv_path IS NOT NULL)
            OR (OLD.csv_path IS NOT NULL AND NEW.csv_path IS NULL)
        ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report file paths are set once and cleared on purge';
    END IF;

    IF NOT (NEW.xlsx_path <=> OLD.xlsx_path)
        AND NOT (
            (OLD.xlsx_path IS NULL AND NEW.xlsx_path IS NOT NULL)
            OR (OLD.xlsx_path IS NOT NULL AND NEW.xlsx_path IS NULL)
        ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report file paths are set once and cleared on purge';
    END IF;

    IF NOT (NEW.pdf_path <=> OLD.pdf_path)
        AND NOT (
            (OLD.pdf_path IS NULL AND NEW.pdf_path IS NOT NULL)
            OR (OLD.pdf_path IS NOT NULL AND NEW.pdf_path IS NULL)
        ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report file paths are set once and cleared on purge';
    END IF;

    IF NOT (NEW.purged_at <=> OLD.purged_at)
        AND NOT (OLD.purged_at IS NULL AND NEW.purged_at IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'report_runs purged_at can only be set once';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS report_runs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS report_runs_prevent_delete');
        Schema::dropIfExists('report_runs');
    }
};

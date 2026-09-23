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
        Schema::create('sales_materials', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('kind', 32);
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('agency_scope')->storedAs('coalesce(`agency_id`, 0)');
            $table->string('file_path', 255)->nullable();
            $table->string('mime', 128);
            $table->unsignedInteger('bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('published')->default(false);
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['title', 'agency_scope', 'version']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER sales_materials_prevent_delete
BEFORE DELETE ON sales_materials
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales_materials cannot be deleted'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER sales_materials_prevent_update
BEFORE UPDATE ON sales_materials
FOR EACH ROW
BEGIN
    IF NOT (NEW.title <=> OLD.title)
        OR NOT (NEW.kind <=> OLD.kind)
        OR NOT (NEW.agency_id <=> OLD.agency_id)
        OR NOT (NEW.version <=> OLD.version)
        OR NOT (NEW.mime <=> OLD.mime)
        OR NOT (NEW.bytes <=> OLD.bytes)
        OR NOT (NEW.uploaded_by <=> OLD.uploaded_by)
        OR NOT (NEW.created_at <=> OLD.created_at)
        OR NOT (NEW.created_by <=> OLD.created_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales_materials columns are immutable';
    END IF;

    IF NOT (NEW.purged_at <=> OLD.purged_at)
        AND NOT (OLD.purged_at IS NULL AND NEW.purged_at IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'sales_materials purged_at can only be set once';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS sales_materials_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS sales_materials_prevent_delete');
        Schema::dropIfExists('sales_materials');
    }
};

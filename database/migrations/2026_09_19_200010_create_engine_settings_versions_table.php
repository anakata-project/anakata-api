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
        Schema::create('engine_settings_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('document');
            $table->json('changes');
            $table->string('approval_reference', 255)->nullable();
            $table->timestamp('published_at', 3);
            $table->timestamps(3);
            $table->auditColumns();
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER engine_settings_versions_prevent_update
BEFORE UPDATE ON engine_settings_versions
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'engine_settings_versions is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER engine_settings_versions_prevent_delete
BEFORE DELETE ON engine_settings_versions
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'engine_settings_versions is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS engine_settings_versions_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS engine_settings_versions_prevent_delete');
        Schema::dropIfExists('engine_settings_versions');
    }
};

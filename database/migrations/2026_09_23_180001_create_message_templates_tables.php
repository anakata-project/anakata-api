<?php

declare(strict_types=1);

use Database\Seeders\MessageTemplatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('message_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('message_templates')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject');
            $table->json('body');
            $table->json('variables');
            $table->boolean('published')->default(false);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('approval_reference', 500)->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'published']);
        });

        Schema::create('template_test_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_template_version_id')->constrained('message_template_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('status', 16);
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER message_template_versions_prevent_update
BEFORE UPDATE ON message_template_versions
FOR EACH ROW
BEGIN
    IF OLD.published = 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'message_template_versions is immutable';
    END IF;
    IF NOT (NEW.template_id <=> OLD.template_id)
        OR NOT (NEW.version <=> OLD.version)
        OR NOT (NEW.subject <=> OLD.subject)
        OR NOT (NEW.body <=> OLD.body)
        OR NOT (NEW.variables <=> OLD.variables) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'message_template_versions content is immutable';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER message_template_versions_prevent_delete
BEFORE DELETE ON message_template_versions
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'message_template_versions is immutable'
SQL);

        (new MessageTemplatesSeeder)->run();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS message_template_versions_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS message_template_versions_prevent_update');
        Schema::dropIfExists('template_test_sends');
        Schema::dropIfExists('message_template_versions');
        Schema::dropIfExists('message_templates');
    }
};

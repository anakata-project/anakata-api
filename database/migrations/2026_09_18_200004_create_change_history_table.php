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
        Schema::create('change_history', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();
            $table->string('event');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at', 3);

            $table->index(['subject_type', 'subject_id', 'id']);
            $table->index('event');
            $table->index('created_at');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER change_history_prevent_update
BEFORE UPDATE ON change_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'change_history is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER change_history_prevent_delete
BEFORE DELETE ON change_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'change_history is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS change_history_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS change_history_prevent_delete');
        Schema::dropIfExists('change_history');
    }
};

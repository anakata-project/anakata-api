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
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('number', 32)->nullable();
            $table->unsignedInteger('version');
            $table->text('reason')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->unsignedBigInteger('payment_key')->storedAs('COALESCE(`payment_id`, 0)');
            $table->json('snapshot');
            $table->string('file_path', 255);
            $table->string('file_sha256', 64);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['booking_id', 'kind', 'payment_key', 'version'], 'documents_booking_kind_payment_version_unique');
            $table->unique(['payment_id', 'kind'], 'documents_payment_kind_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER documents_prevent_update
BEFORE UPDATE ON documents
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'documents is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER documents_prevent_delete
BEFORE DELETE ON documents
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'documents is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS documents_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS documents_prevent_delete');
        Schema::dropIfExists('documents');
    }
};

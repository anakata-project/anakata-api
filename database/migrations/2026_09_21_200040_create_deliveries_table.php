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
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('idempotency_key')->unique();
            $table->json('to');
            $table->json('cc');
            $table->string('subject');
            $table->string('status');
            $table->text('error')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('triggered_by');
            $table->timestamps();
            $table->auditColumns();

            $table->index(['booking_id', 'created_at']);
            $table->index('status');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER deliveries_prevent_delete
BEFORE DELETE ON deliveries
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'deliveries is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER deliveries_prevent_immutable_update
BEFORE UPDATE ON deliveries
FOR EACH ROW
BEGIN
    IF NEW.booking_id <> OLD.booking_id
        OR NOT (NEW.document_id <=> OLD.document_id)
        OR NEW.kind <> OLD.kind
        OR NEW.idempotency_key <> OLD.idempotency_key
        OR CAST(NEW.to AS CHAR) <> CAST(OLD.to AS CHAR)
        OR CAST(NEW.cc AS CHAR) <> CAST(OLD.cc AS CHAR)
        OR NEW.subject <> OLD.subject
        OR NEW.triggered_by <> OLD.triggered_by THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'deliveries identifying columns are immutable';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS deliveries_prevent_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS deliveries_prevent_delete');
        Schema::dropIfExists('deliveries');
    }
};

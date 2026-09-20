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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('method');
            $table->integer('amount');
            $table->string('reference')->unique();
            $table->string('gateway_id')->nullable();
            $table->string('status');
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index('paid_at');
            $table->index('status');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER payments_prevent_delete
BEFORE DELETE ON payments
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER payments_prevent_immutable_update
BEFORE UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.booking_id <> OLD.booking_id
        OR NEW.kind <> OLD.kind
        OR NEW.amount <> OLD.amount
        OR NEW.reference <> OLD.reference THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments ledger columns are immutable';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payments_prevent_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS payments_prevent_delete');
        Schema::dropIfExists('payments');
    }
};

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
        Schema::create('commission_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->integer('amount');
            $table->date('paid_on');
            $table->string('bank_reference');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER commission_payouts_prevent_update
BEFORE UPDATE ON commission_payouts
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'commission_payouts is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER commission_payouts_prevent_delete
BEFORE DELETE ON commission_payouts
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'commission_payouts is append-only'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS commission_payouts_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS commission_payouts_prevent_delete');
        Schema::dropIfExists('commission_payouts');
    }
};

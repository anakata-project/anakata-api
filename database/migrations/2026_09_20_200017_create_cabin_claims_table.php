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
        Schema::create('cabin_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('departure_id')->constrained('departures')->restrictOnDelete();
            $table->foreignId('cabin_id')->constrained('cabins')->restrictOnDelete();
            $table->morphs('holder');
            $table->string('kind', 16);
            $table->string('hold_type', 16)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 16)->nullable();
            $table->string('active_key', 64)
                ->nullable()
                ->storedAs("if(`released_at` is null, concat(`departure_id`, '-', `cabin_id`), null)")
                ->unique();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['departure_id', 'released_at']);
            $table->index(['kind', 'expires_at']);
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER cabin_claims_prevent_delete
BEFORE DELETE ON cabin_claims
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cabin_claims cannot be deleted'
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cabin_claims_prevent_delete');
        Schema::dropIfExists('cabin_claims');
    }
};

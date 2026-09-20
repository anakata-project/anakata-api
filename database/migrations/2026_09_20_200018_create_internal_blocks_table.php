<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->string('reason', 32);
            $table->text('notes')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_note')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_blocks');
    }
};

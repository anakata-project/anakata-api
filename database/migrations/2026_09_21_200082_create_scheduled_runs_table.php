<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command', 191)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('outcome', 16);
            $table->unsignedSmallInteger('exit_code')->nullable();
            $table->string('output', 500)->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['command', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_runs');
    }
};

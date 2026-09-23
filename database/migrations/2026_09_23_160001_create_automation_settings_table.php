<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->text('disabled_reason')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_settings');
    }
};

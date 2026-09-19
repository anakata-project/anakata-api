<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_config_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('document');
            $table->json('changes');
            $table->string('approval_reference', 255)->nullable();
            $table->timestamp('published_at', 3);
            $table->timestamps(3);
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_config_versions');
    }
};

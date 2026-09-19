<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->unique();
            $table->unsignedInteger('last_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
    }
};

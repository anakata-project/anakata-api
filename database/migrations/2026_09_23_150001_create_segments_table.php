<?php

declare(strict_types=1);

use Database\Seeders\SegmentsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('sentence', 500);
            $table->json('conditions');
            $table->json('dimensions');
            $table->string('kind', 32);
            $table->boolean('system')->default(false);
            $table->boolean('active')->default(true);
            $table->string('feeds', 500);
            $table->timestamps();
            $table->auditColumns();
            $table->index('kind');
            $table->index('active');
        });

        (new SegmentsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};

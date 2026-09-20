<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itineraries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 40);
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedInteger('sort_order')->default(9);
            $table->boolean('festive')->default(false);
            $table->unsignedTinyInteger('days');
            $table->unsignedTinyInteger('nights');
            $table->string('embark');
            $table->string('disembark');
            $table->string('tagline');
            $table->string('hero_image_path')->nullable();
            $table->string('hero_alt');
            $table->string('fallback_gradient');
            $table->string('card_description', 220);
            $table->text('overview');
            $table->text('long_description');
            $table->json('highlights');
            $table->json('chips');
            $table->json('facts');
            $table->json('day_plan');
            $table->json('included');
            $table->json('excluded');
            $table->json('faqs');
            $table->string('slug')->nullable()->unique();
            $table->string('meta_title', 60);
            $table->string('meta_description', 155);
            $table->timestamps();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};

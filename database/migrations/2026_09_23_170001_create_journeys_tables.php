<?php

declare(strict_types=1);

use Database\Seeders\JourneysSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journeys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('goal', 500);
            $table->string('kind', 32);
            $table->string('subject', 16);
            $table->json('trigger');
            $table->json('exit_conditions');
            $table->string('exit_sentence', 500);
            $table->string('contract', 500)->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('system')->default(false);
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('journey_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_id')->constrained('journeys')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->json('delay');
            $table->string('template_key', 64);
            $table->json('condition')->nullable();
            $table->string('action', 16);
            $table->string('catalogue_key', 64)->nullable();
            $table->json('catalogue_keys')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->unique(['journey_id', 'position']);
        });

        Schema::create('journey_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_id')->constrained('journeys')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->unsignedBigInteger('booking_subject')->storedAs('ifnull(`booking_id`, 0)');
            $table->unsignedSmallInteger('position');
            $table->timestamp('next_due_at')->nullable();
            $table->string('status', 16);
            $table->string('exit_reason', 500)->nullable();
            $table->timestamp('enrolled_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->unique(['journey_id', 'contact_id', 'booking_subject']);
            $table->index(['status', 'next_due_at']);
        });

        Schema::create('journey_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journey_enrolment_id')->constrained('journey_enrolments')->cascadeOnDelete();
            $table->foreignId('journey_step_id')->constrained('journey_steps')->cascadeOnDelete();
            $table->string('template_key', 64);
            $table->unsignedInteger('template_version')->nullable();
            $table->string('catalogue_key', 64)->nullable();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->auditColumns();
            $table->index('journey_enrolment_id');
        });

        (new JourneysSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_sends');
        Schema::dropIfExists('journey_enrolments');
        Schema::dropIfExists('journey_steps');
        Schema::dropIfExists('journeys');
    }
};

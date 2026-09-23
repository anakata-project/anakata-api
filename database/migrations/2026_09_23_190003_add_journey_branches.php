<?php

declare(strict_types=1);

use Database\Seeders\JourneysSeeder;
use Database\Seeders\MessageTemplatesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_steps', function (Blueprint $table): void {
            $table->string('branch', 32)->default('lead')->after('journey_id');
        });

        Schema::table('journey_enrolments', function (Blueprint $table): void {
            $table->string('branch', 32)->default('lead')->after('booking_subject');
        });

        Schema::table('journey_steps', function (Blueprint $table): void {
            $table->dropForeign(['journey_id']);
            $table->dropUnique(['journey_id', 'position']);
            $table->unique(['journey_id', 'branch', 'position']);
            $table->foreign('journey_id')->references('id')->on('journeys')->cascadeOnDelete();
        });

        Schema::table('journey_enrolments', function (Blueprint $table): void {
            $table->dropForeign(['journey_id']);
            $table->dropUnique(['journey_id', 'contact_id', 'booking_subject']);
            $table->unique(
                ['journey_id', 'contact_id', 'booking_subject', 'branch'],
                'journey_enrolments_subject_branch_unique',
            );
            $table->foreign('journey_id')->references('id')->on('journeys')->cascadeOnDelete();
        });

        (new JourneysSeeder)->run();
        (new MessageTemplatesSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('journey_enrolments', function (Blueprint $table): void {
            $table->dropForeign(['journey_id']);
            $table->dropUnique('journey_enrolments_subject_branch_unique');
            $table->unique(['journey_id', 'contact_id', 'booking_subject']);
            $table->foreign('journey_id')->references('id')->on('journeys')->cascadeOnDelete();
            $table->dropColumn('branch');
        });

        Schema::table('journey_steps', function (Blueprint $table): void {
            $table->dropForeign(['journey_id']);
            $table->dropUnique(['journey_id', 'branch', 'position']);
            $table->unique(['journey_id', 'position']);
            $table->foreign('journey_id')->references('id')->on('journeys')->cascadeOnDelete();
            $table->dropColumn('branch');
        });
    }
};

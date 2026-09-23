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
        Schema::create('report_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('definition_key', 64);
            $table->string('cadence', 16);
            $table->time('send_at');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->json('parameters');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['definition_key', 'cadence']);
        });

        Schema::create('report_run_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_run_id')->constrained('report_runs')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['report_run_id', 'user_id']);
        });

        Schema::table('report_runs', function (Blueprint $table): void {
            $table->foreign('subscription_id')->references('id')->on('report_subscriptions')->nullOnDelete();
        });

        $now = now();

        DB::table('report_subscriptions')->insert([
            [
                'definition_key' => 'commercial-summary',
                'cadence' => 'DAILY',
                'send_at' => '08:00:00',
                'weekday' => null,
                'day_of_month' => null,
                'parameters' => json_encode(['window' => 'yesterday'], JSON_THROW_ON_ERROR),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'definition_key' => 'occupancy',
                'cadence' => 'WEEKLY',
                'send_at' => '09:00:00',
                'weekday' => 1,
                'day_of_month' => null,
                'parameters' => json_encode(['window' => 'last_week'], JSON_THROW_ON_ERROR),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'definition_key' => 'pipeline-summary',
                'cadence' => 'MONTHLY',
                'send_at' => '08:00:00',
                'weekday' => null,
                'day_of_month' => 1,
                'parameters' => json_encode(['window' => 'last_month'], JSON_THROW_ON_ERROR),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'definition_key' => 'agency-report',
                'cadence' => 'QUARTERLY',
                'send_at' => '08:00:00',
                'weekday' => null,
                'day_of_month' => 1,
                'parameters' => json_encode(['window' => 'last_quarter'], JSON_THROW_ON_ERROR),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('report_runs', function (Blueprint $table): void {
            $table->dropForeign(['subscription_id']);
        });

        Schema::dropIfExists('report_run_notifications');
        Schema::dropIfExists('report_subscriptions');
    }
};

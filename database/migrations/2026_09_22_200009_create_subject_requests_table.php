<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('type', 32);
            $table->timestamp('received_at');
            $table->timestamp('due_at');
            $table->string('channel', 32);
            $table->text('verified_how')->nullable();
            $table->string('status', 32);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamps();
            $table->auditColumns();
            $table->index(['status', 'due_at']);
            $table->index('contact_id');
        });

        Schema::create('erasure_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->char('email_sha256', 64);
            $table->timestamp('erased_at');
            $table->foreignId('erased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->foreign('subject_request_id')->references('id')->on('subject_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->dropForeign(['subject_request_id']);
        });

        Schema::dropIfExists('erasure_log');
        Schema::dropIfExists('subject_requests');
    }
};

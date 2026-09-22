<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('context', 500);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('charter_enquiry_id')->nullable()->constrained('charter_enquiries')->nullOnDelete();
            $table->foreignId('refund_request_id')->nullable()->constrained('refund_requests')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('subject_request_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('needs_permission', 64)->nullable();
            $table->timestamp('due_at');
            $table->string('source', 16);
            $table->string('kind', 32);
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('status', 16);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('outcome')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['status', 'due_at']);
            $table->index(['owner_id', 'status']);
        });

        Schema::create('contact_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('kind', 32);
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('crm_task_id')->nullable()->constrained('crm_tasks')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['contact_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_activities');
        Schema::dropIfExists('crm_tasks');
    }
};

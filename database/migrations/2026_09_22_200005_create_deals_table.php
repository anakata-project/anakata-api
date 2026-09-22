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
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type', 16);
            $table->string('stage', 32)->nullable();
            $table->timestamp('stage_entered_at');
            $table->unsignedInteger('estimate')->nullable();
            $table->text('lost_reason')->nullable();
            $table->foreignId('booking_id')->nullable()->unique()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->unique()->constrained('groups')->restrictOnDelete();
            $table->foreignId('charter_enquiry_id')->nullable()->unique()->constrained('charter_enquiries')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['contact_id', 'stage']);
        });

        DB::statement('ALTER TABLE deals ADD CONSTRAINT deals_one_binding CHECK (booking_id IS NULL OR group_id IS NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};

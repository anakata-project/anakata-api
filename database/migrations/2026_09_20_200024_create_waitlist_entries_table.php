<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('departure_id')->constrained()->restrictOnDelete();
            $table->string('cabin_category');
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children');
            $table->text('notes')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notified_channel')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('removed_reason')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->index(['departure_id', 'cabin_category', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->boolean('is_lead')->default(false);
            $table->unsignedBigInteger('lead_key')
                ->nullable()
                ->storedAs('if(`is_lead`, `booking_id`, null)')
                ->unique();
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->date('dob')->nullable();
            $table->char('nationality', 2)->nullable();
            $table->boolean('ecuador_resident')->default(false);
            $table->text('passport_no')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('email')->nullable();
            $table->boolean('insurance_declared')->default(false);
            $table->text('medical_note')->nullable();
            $table->text('dietary_note')->nullable();
            $table->text('accessibility_note')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship')->nullable();
            $table->timestamp('guardian_consented_at')->nullable();
            $table->foreignId('guardian_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('png_category', 32)->nullable();
            $table->unsignedInteger('png_fee')->nullable();
            $table->timestamps();
            $table->auditColumns();

            $table->unique(['booking_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};

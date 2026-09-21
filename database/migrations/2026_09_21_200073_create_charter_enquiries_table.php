<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charter_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->date('preferred_from')->nullable();
            $table->date('preferred_to')->nullable();
            $table->foreignId('departure_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('guests');
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->text('message');
            $table->string('source');
            $table->string('status');
            $table->timestamps();
            $table->auditColumns();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charter_enquiries');
    }
};

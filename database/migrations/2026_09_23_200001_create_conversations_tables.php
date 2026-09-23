<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('subject', 500);
            $table->timestamp('last_message_at');
            $table->string('status', 16);
            $table->boolean('unread')->default(false);
            $table->timestamps();
            $table->auditColumns();
            $table->index(['contact_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('direction', 8);
            $table->string('from', 320);
            $table->json('to');
            $table->string('subject', 500);
            $table->longText('body_html');
            $table->longText('body_text');
            $table->string('message_id', 512)->nullable()->unique();
            $table->string('in_reply_to', 512)->nullable();
            $table->timestamp('sent_at');
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->auditColumns();
            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};

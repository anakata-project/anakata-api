<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_users', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('accepted_at');
            $table->rememberToken()->after('last_login_at');
            $table->string('invite_token_hash')->nullable()->after('remember_token');
            $table->timestamp('invite_sent_at')->nullable()->after('invite_token_hash');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_sent_at');
            $table->foreignId('invited_by')->nullable()->after('invite_expires_at')->constrained('users')->nullOnDelete();

            $table->index('invite_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('agency_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn([
                'password',
                'accepted_at',
                'last_login_at',
                'remember_token',
                'invite_token_hash',
                'invite_sent_at',
                'invite_expires_at',
            ]);
        });
    }
};

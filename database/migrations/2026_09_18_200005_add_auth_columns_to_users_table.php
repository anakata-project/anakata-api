<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 16)->default('invited')->after('password');
            $table->timestamp('invited_at')->nullable()->after('status');
            $table->timestamp('activated_at')->nullable()->after('invited_at');
            $table->timestamp('disabled_at')->nullable()->after('activated_at');
            $table->timestamp('last_login_at')->nullable()->after('disabled_at');
            $table->index('status');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'invited_at',
                'activated_at',
                'disabled_at',
                'last_login_at',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};

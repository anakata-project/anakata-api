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
        $emails = DB::table('users')
            ->whereNull('role_id')
            ->orderBy('email')
            ->pluck('email');

        if ($emails->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot make users.role_id NOT NULL while these users have no role: '.$emails->implode(', ')
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable()->change();
        });
    }
};

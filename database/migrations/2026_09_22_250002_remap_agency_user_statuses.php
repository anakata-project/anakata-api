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
        DB::table('agency_users')->where('status', 'PENDING')->update([
            'status' => 'INVITE_ON_APPROVAL',
        ]);
        DB::table('agency_users')->where('status', 'INVITED')->update([
            'status' => 'INVITE_ON_PORTAL_LAUNCH',
        ]);

        Schema::table('agency_users', function (Blueprint $table): void {
            $table->unique(['agency_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('agency_users', function (Blueprint $table): void {
            $table->dropUnique(['agency_id', 'email']);
        });

        DB::table('agency_users')->where('status', 'INVITE_ON_APPROVAL')->update([
            'status' => 'PENDING',
        ]);
        DB::table('agency_users')->where('status', 'INVITE_ON_PORTAL_LAUNCH')->update([
            'status' => 'INVITED',
        ]);
    }
};

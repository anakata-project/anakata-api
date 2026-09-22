<?php

declare(strict_types=1);

use App\Support\Roles\GrantCommissionsRecordPayout;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only. Adds commissions.record_payout to an existing external-finance role
 * once. DemoUsersSeeder creates that role in local and testing; this covers a
 * role that already exists when the migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        GrantCommissionsRecordPayout::grant();
    }

    public function down(): void
    {
        // The finance role keeps the permission; do not strip it on rollback.
    }
};

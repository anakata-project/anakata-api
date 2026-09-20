<?php

declare(strict_types=1);

use App\Support\Roles\GrantAgenciesManage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantAgenciesManage::grant();
    }

    public function down(): void
    {
        // Manager defaults now include agencies.manage; do not strip it on rollback.
    }
};

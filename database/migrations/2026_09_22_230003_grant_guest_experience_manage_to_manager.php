<?php

declare(strict_types=1);

use App\Support\Roles\GrantGuestExperienceManage;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. Adds guest_experience.manage to an existing
 * manager role once. RolesSeeder never updates existing roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        GrantGuestExperienceManage::grant();
    }

    public function down(): void
    {
        // Manager defaults now include guest_experience.manage; do not strip it on rollback.
    }
};

<?php

declare(strict_types=1);

use App\Support\Roles\GrantGuestsViewSensitive;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. Adds guests.view_sensitive to an existing
 * manager role once. RolesSeeder never updates existing roles; later admin
 * removals of this personal-data permission (LEG-002) must stick.
 */
return new class extends Migration
{
    public function up(): void
    {
        GrantGuestsViewSensitive::grant();
    }

    public function down(): void
    {
        // Manager defaults now include guests.view_sensitive; do not strip it on rollback.
    }
};

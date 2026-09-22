<?php

declare(strict_types=1);

use App\Support\Roles\GrantConsentsRecord;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantConsentsRecord::grant();
    }

    public function down(): void
    {
        // Role defaults now include consents.record; do not strip it on rollback.
    }
};

<?php

declare(strict_types=1);

use App\Support\Roles\GrantContactsMerge;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantContactsMerge::grant();
    }

    public function down(): void
    {
        // Role defaults now include contacts.merge; do not strip it on rollback.
    }
};

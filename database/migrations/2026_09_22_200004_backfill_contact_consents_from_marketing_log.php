<?php

declare(strict_types=1);

use App\Support\Crm\BackfillContactConsents;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        BackfillContactConsents::run();
    }

    public function down(): void
    {
        // Register rows are append-only.
    }
};

<?php

declare(strict_types=1);

use App\Support\Roles\GrantContactsManage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantContactsManage::grant();
    }

    public function down(): void
    {
        // Role defaults now include contacts.manage; do not strip it on rollback.
    }
};

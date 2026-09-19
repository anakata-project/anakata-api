<?php

declare(strict_types=1);

use App\Support\Roles\GrantEngineCopyManage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantEngineCopyManage::grant();
    }

    public function down(): void
    {
        GrantEngineCopyManage::revoke();
    }
};

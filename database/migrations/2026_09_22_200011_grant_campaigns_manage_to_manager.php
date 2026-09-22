<?php

declare(strict_types=1);

use App\Support\Roles\GrantCampaignsManage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GrantCampaignsManage::grant();
    }

    public function down(): void
    {
        // Role defaults now include campaigns.manage; do not strip it on rollback.
    }
};

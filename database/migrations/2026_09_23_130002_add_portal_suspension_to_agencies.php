<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->timestamp('portal_suspended_at')->nullable()->after('decision_reason');
            $table->foreignId('portal_suspended_by')->nullable()->after('portal_suspended_at')->constrained('users')->nullOnDelete();
            $table->text('portal_suspend_reason')->nullable()->after('portal_suspended_by');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_suspended_by');
            $table->dropColumn(['portal_suspended_at', 'portal_suspend_reason']);
        });
    }
};

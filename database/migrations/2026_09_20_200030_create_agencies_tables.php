<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('contact');
            $table->string('email');
            $table->char('country', 2)->nullable();
            $table->string('network')->nullable();
            $table->unsignedTinyInteger('commission_pct');
            $table->string('payment_terms');
            $table->string('status');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('agency_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('status');
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('agency_id')->nullable()->after('owner_id')->constrained('agencies')->nullOnDelete();
            $table->unsignedTinyInteger('commission_pct')->nullable()->after('agency_id');
            $table->boolean('commission_approved')->default(false)->after('commission_pct');
            $table->foreignId('commission_approved_by')->nullable()->after('commission_approved')->constrained('users')->nullOnDelete();
            $table->timestamp('commission_approved_at')->nullable()->after('commission_approved_by');
            $table->text('commission_reason')->nullable()->after('commission_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropConstrainedForeignId('commission_approved_by');
            $table->dropColumn([
                'commission_pct',
                'commission_approved',
                'commission_approved_at',
                'commission_reason',
            ]);
        });

        Schema::dropIfExists('agency_users');
        Schema::dropIfExists('agencies');
    }
};

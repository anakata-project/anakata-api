<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charter_enquiries', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->string('accepted_name')->nullable()->after('accepted_at');
            $table->foreignId('booking_id')->nullable()->after('accepted_name')->constrained('bookings')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->date('deposit_due_on')->nullable()->after('deposit_pct');
        });

        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->string('band_source', 16)->nullable()->after('band_min_days');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE documents MODIFY booking_id BIGINT UNSIGNED NULL');

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreignId('charter_enquiry_id')->nullable()->after('booking_id')->constrained('charter_enquiries')->restrictOnDelete();
            $table->unique(['charter_enquiry_id', 'kind', 'version'], 'documents_enquiry_kind_version_unique');
        });

        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE booking_access_tokens MODIFY booking_id BIGINT UNSIGNED NULL');

        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->foreignId('charter_enquiry_id')->nullable()->after('booking_id')->constrained('charter_enquiries')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->after('charter_enquiry_id')->constrained('documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['document_id']);
            $table->dropForeign(['charter_enquiry_id']);
            $table->dropColumn(['document_id', 'charter_enquiry_id']);
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE booking_access_tokens MODIFY booking_id BIGINT UNSIGNED NOT NULL');

        Schema::table('booking_access_tokens', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique('documents_enquiry_kind_version_unique');
            $table->dropForeign(['charter_enquiry_id']);
            $table->dropColumn('charter_enquiry_id');
            $table->dropForeign(['booking_id']);
        });

        DB::statement('ALTER TABLE documents MODIFY booking_id BIGINT UNSIGNED NOT NULL');

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });

        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->dropColumn('band_source');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('deposit_due_on');
        });

        Schema::table('charter_enquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn(['accepted_at', 'accepted_name']);
        });
    }
};

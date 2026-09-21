<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Support\Contacts\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('last_touch')->constrained('contacts')->restrictOnDelete();
            $table->index('phone_e164');
        });

        Schema::create('contact_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survivor_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('loser_id')->constrained('contacts')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at');
            $table->json('repointed_rows');
            $table->json('merged_identifiers')->nullable();
            $table->json('loser_fields')->nullable();
            $table->json('survivor_filled');
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('undo_reason')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();
            $table->auditColumns();
        });

        Schema::create('contact_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alias_id')->unique()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('merge_id')->constrained('contact_merges')->restrictOnDelete();
            $table->timestamps();
            $table->auditColumns();
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER contact_merges_prevent_delete
BEFORE DELETE ON contact_merges
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contact_merges is append-only'
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER contact_merges_prevent_immutable_update
BEFORE UPDATE ON contact_merges
FOR EACH ROW
BEGIN
    IF NEW.survivor_id <> OLD.survivor_id
        OR NEW.loser_id <> OLD.loser_id
        OR NEW.reason <> OLD.reason
        OR NOT (NEW.merged_by <=> OLD.merged_by)
        OR NEW.merged_at <> OLD.merged_at
        OR NOT (NEW.repointed_rows <=> OLD.repointed_rows)
        OR NOT (NEW.survivor_filled <=> OLD.survivor_filled)
        OR NEW.created_at <> OLD.created_at
        OR NOT (NEW.created_by <=> OLD.created_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contact_merges columns are immutable';
    END IF;

    IF (NEW.merged_identifiers <=> OLD.merged_identifiers)
        AND (NEW.loser_fields <=> OLD.loser_fields)
        AND (NEW.erased_at <=> OLD.erased_at) THEN
        SET @identity_unchanged = 1;
    ELSE
        SET @identity_unchanged = 0;
    END IF;

    SET @erase_ok = OLD.erased_at IS NULL
        AND NEW.erased_at IS NOT NULL
        AND NEW.merged_identifiers IS NULL
        AND NEW.loser_fields IS NULL;

    IF @identity_unchanged = 0 AND @erase_ok = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'contact_merges identifiers are immutable except on erase';
    END IF;
END
SQL);

        Contact::query()->orderBy('id')->each(function (Contact $contact): void {
            $e164 = PhoneNumber::toE164($contact->phone, $contact->country);

            if ($e164 === $contact->phone_e164) {
                return;
            }

            $contact->forceFill(['phone_e164' => $e164])->saveQuietly();
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS contact_merges_prevent_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS contact_merges_prevent_delete');
        Schema::dropIfExists('contact_aliases');
        Schema::dropIfExists('contact_merges');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropIndex(['phone_e164']);
        });
    }
};

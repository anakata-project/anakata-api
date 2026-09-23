<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Support\Templates\UnsubscribeLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->char('unsubscribe_token', 64)->nullable()->unique()->after('email');
        });

        Contact::query()->orderBy('id')->each(function (Contact $contact): void {
            DB::table('contacts')->where('id', $contact->id)->update([
                'unsubscribe_token' => UnsubscribeLink::token($contact),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn('unsubscribe_token');
        });
    }
};

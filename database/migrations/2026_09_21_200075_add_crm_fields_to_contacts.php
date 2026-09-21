<?php

declare(strict_types=1);

use App\Actions\Contacts\ResolveContact;
use App\Enums\ContactType;
use App\Models\Agency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('type')->default(ContactType::DirectPassenger->value)->after('preferred_channel');
            $table->char('language', 2)->default('en')->after('type');
            $table->string('phone_e164')->nullable()->after('language');
            $table->json('first_touch')->nullable()->after('phone_e164');
            $table->json('last_touch')->nullable()->after('first_touch');
        });

        $contacts = app(ResolveContact::class);

        Agency::query()->orderBy('id')->each(function (Agency $agency) use ($contacts): void {
            $name = $agency->contact !== '' ? $agency->contact : $agency->name;

            $contacts->handle([
                'name' => $name,
                'email' => $agency->email,
                'country' => $agency->country,
                'type' => ContactType::TravelAgent,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['type', 'language', 'phone_e164', 'first_touch', 'last_touch']);
        });
    }
};

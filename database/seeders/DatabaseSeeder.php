<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(ConfigSeeder::class);
        $this->call(InventorySeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUsersSeeder::class);
            $this->call(DemoInventorySeeder::class);
            $this->call(DemoBookingsSeeder::class);
            $this->call(DemoAgenciesSeeder::class);
            $this->call(DemoRequestsSeeder::class);
            $this->call(DemoGuestsSeeder::class);
            $this->call(DemoConsentsSeeder::class);
            $this->call(DemoExtrasSeeder::class);
            $this->call(DemoDocumentsSeeder::class);
        }
    }
}

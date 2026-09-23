<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesMaterialKind;
use App\Models\SalesMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesMaterial>
 */
class SalesMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Fact sheet',
            'kind' => SalesMaterialKind::FactSheet,
            'agency_id' => null,
            'version' => 1,
            'file_path' => 'fact-sheet.pdf',
            'mime' => 'application/pdf',
            'bytes' => 128,
            'uploaded_by' => User::factory(),
            'published' => true,
            'purged_at' => null,
        ];
    }
}

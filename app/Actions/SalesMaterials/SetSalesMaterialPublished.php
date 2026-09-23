<?php

declare(strict_types=1);

namespace App\Actions\SalesMaterials;

use App\Actions\Action;
use App\Models\SalesMaterial;
use App\Models\User;
use App\Support\History\History;

final class SetSalesMaterialPublished extends Action
{
    public function handle(SalesMaterial $material, User $actor): SalesMaterial
    {
        return $this->transaction(function () use ($material, $actor): SalesMaterial {
            $before = $material->published;
            $material->published = ! $before;
            $material->save();

            History::record(
                $material,
                $material->published ? 'sales_material.published' : 'sales_material.unpublished',
                before: ['published' => $before],
                after: ['published' => $material->published, 'version' => $material->version],
                actor: $actor,
            );

            return $material->fresh(['uploadedBy']) ?? $material;
        });
    }
}

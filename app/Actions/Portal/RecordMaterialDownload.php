<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Actions\Action;
use App\Models\AgencyUser;
use App\Models\SalesMaterial;
use App\Support\History\History;

final class RecordMaterialDownload extends Action
{
    public function handle(AgencyUser $actor, SalesMaterial $material): void
    {
        $this->transaction(function () use ($actor, $material): void {
            $actor->loadMissing('agency');

            History::record($actor->agency, 'portal.material_downloaded', after: [
                'material_id' => $material->id,
                'title' => $material->title,
                'version' => $material->version,
            ], actorLabel: "{$actor->name} ({$actor->email})", extraContext: [
                'agency_user_id' => $actor->id,
            ]);
        });
    }
}

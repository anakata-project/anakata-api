<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\RecordMaterialDownload;
use App\Http\Resources\Portal\PortalSalesMaterialResource;
use App\Models\SalesMaterial;
use App\Support\Agencies\PortalPreview;
use App\Support\SalesMaterials\MaterialFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PortalSalesMaterialController extends PortalController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $agency = $this->agency($request);

        $materials = SalesMaterial::query()
            ->visibleTo($agency)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $collection = PortalSalesMaterialResource::collection($materials);

        if ($materials->isEmpty()) {
            $collection->additional([
                'meta' => [
                    'note' => PortalPreview::MATERIALS_NOTE,
                ],
            ]);
        }

        return $collection;
    }

    public function file(Request $request, SalesMaterial $material, RecordMaterialDownload $downloads): StreamedResponse
    {
        $agency = $this->agency($request);
        $visible = SalesMaterial::query()->visibleTo($agency)->whereKey($material->id)->exists();

        abort_unless($visible, 404);

        $path = $material->file_path;
        abort_if(! is_string($path) || $path === '' || ! Storage::disk('materials')->exists($path), 404);

        $downloads->handle($this->agencyUser($request), $material);

        return MaterialFile::stream($material);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Actions\SalesMaterials\SetSalesMaterialPublished;
use App\Actions\SalesMaterials\UploadSalesMaterial;
use App\Enums\SalesMaterialKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\IndexSalesMaterialsRequest;
use App\Http\Requests\Rms\StoreSalesMaterialRequest;
use App\Http\Resources\Rms\SalesMaterialResource;
use App\Models\SalesMaterial;
use App\Models\User;
use App\Support\SalesMaterials\MaterialFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SalesMaterialController extends Controller
{
    public function index(IndexSalesMaterialsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesMaterial::class);

        $agencyId = $request->validated('agency_id');

        $materials = SalesMaterial::query()
            ->with('uploadedBy')
            ->when(is_numeric($agencyId), function (Builder $query) use ($agencyId): void {
                $id = (int) $agencyId;
                $query->where(function (Builder $inner) use ($id): void {
                    $inner->whereNull('agency_id')->orWhere('agency_id', $id);
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return SalesMaterialResource::collection($materials);
    }

    public function store(StoreSalesMaterialRequest $request, UploadSalesMaterial $action): Response
    {
        $this->authorize('create', SalesMaterial::class);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $agencyId = $request->validated('agency_id');

        $material = $action->handle(
            $actor,
            (string) $request->validated('title'),
            SalesMaterialKind::from((string) $request->validated('kind')),
            is_numeric($agencyId) ? (int) $agencyId : null,
            $file,
        );

        return (new SalesMaterialResource($material))->response()->setStatusCode(201);
    }

    public function update(SalesMaterial $material, SetSalesMaterialPublished $action): SalesMaterialResource
    {
        $this->authorize('update', $material);

        $actor = request()->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return new SalesMaterialResource($action->handle($material, $actor));
    }

    public function file(SalesMaterial $material): StreamedResponse
    {
        $this->authorize('download', $material);

        return MaterialFile::stream($material);
    }
}

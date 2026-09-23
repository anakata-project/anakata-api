<?php

declare(strict_types=1);

namespace App\Actions\SalesMaterials;

use App\Actions\Action;
use App\Enums\SalesMaterialKind;
use App\Models\SalesMaterial;
use App\Models\User;
use App\Support\History\History;
use App\Support\SalesMaterials\MaterialFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UploadSalesMaterial extends Action
{
    public function handle(User $actor, string $title, SalesMaterialKind $kind, ?int $agencyId, UploadedFile $file): SalesMaterial
    {
        $mime = MaterialFile::detect($file);

        if ($mime === null || ! array_key_exists($mime, MaterialFile::EXTENSIONS)) {
            throw ValidationException::withMessages([
                'file' => ['The file must be a PDF, PNG, JPG, MP4, or ZIP.'],
            ]);
        }

        $size = $file->getSize();

        if ($size === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be read.'],
            ]);
        }

        $path = MaterialFile::store($file, $mime);

        try {
            return $this->transaction(function () use ($actor, $title, $kind, $agencyId, $mime, $size, $path): SalesMaterial {
                $series = SalesMaterial::query()
                    ->when(
                        $agencyId === null,
                        fn (Builder $query) => $query->whereNull('agency_id'),
                        fn (Builder $query) => $query->where('agency_id', $agencyId),
                    )
                    ->where('title', $title)
                    ->lockForUpdate()
                    ->get();

                $material = SalesMaterial::query()->create([
                    'title' => $title,
                    'kind' => $kind,
                    'agency_id' => $agencyId,
                    'version' => ((int) $series->max('version')) + 1,
                    'file_path' => $path,
                    'mime' => $mime,
                    'bytes' => $size,
                    'uploaded_by' => $actor->id,
                    'published' => true,
                ]);

                History::record($material, 'sales_material.uploaded', after: [
                    'title' => $material->title,
                    'kind' => $material->kind->value,
                    'agency_id' => $material->agency_id,
                    'version' => $material->version,
                    'mime' => $material->mime,
                    'bytes' => $material->bytes,
                    'published' => true,
                ], actor: $actor);

                foreach ($series as $older) {
                    if (! $older->published) {
                        continue;
                    }

                    $older->published = false;
                    $older->save();

                    History::record($older, 'sales_material.unpublished', before: [
                        'published' => true,
                    ], after: [
                        'published' => false,
                        'version' => $older->version,
                    ], actor: $actor);
                }

                return $material->fresh(['uploadedBy']) ?? $material;
            });
        } catch (Throwable $e) {
            Storage::disk('materials')->delete($path);

            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Manifests;

use App\Actions\Action;
use App\Enums\ManifestKind;
use App\Enums\ManifestReason;
use App\Models\Departure;
use App\Models\Manifest;
use App\Models\User;
use App\Support\History\History;
use App\Support\Manifests\ManifestDue;
use App\Support\Manifests\ManifestFiles;
use App\Support\Manifests\ManifestHash;
use App\Support\Manifests\ManifestPassenger;
use App\Support\Manifests\ManifestRoster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class IssueManifest extends Action
{
    public function __construct(private readonly ManifestFiles $files) {}

    /**
     * @return array{created: bool, manifest: Manifest}
     */
    public function request(Departure $departure, ManifestKind $kind, User $actor): array
    {
        /** @var array{created: bool, manifest: Manifest} $result */
        $result = $this->transaction(function () use ($departure, $kind, $actor): array {
            $this->lock($departure);
            $passengers = ManifestRoster::passengers($departure);

            if ($passengers->isEmpty()) {
                throw ValidationException::withMessages([
                    'departure' => ['This departure has no passengers on a manifest.'],
                ]);
            }

            $hash = ManifestHash::of($kind, $passengers);
            $latest = $this->latest($departure, $kind);

            if ($latest instanceof Manifest && $latest->snapshot_hash === $hash) {
                return ['created' => false, 'manifest' => $latest];
            }

            $reason = $latest instanceof Manifest
                ? ManifestReason::PassengerChange
                : ManifestReason::Requested;

            return [
                'created' => true,
                'manifest' => $this->write($departure, $kind, $reason, $passengers, $hash, $actor, false),
            ];
        });

        return $result;
    }

    public function first(Departure $departure, ManifestKind $kind): ?Manifest
    {
        /** @var Manifest|null $manifest */
        $manifest = $this->transaction(function () use ($departure, $kind): ?Manifest {
            $this->lock($departure);

            if ($this->latest($departure, $kind) instanceof Manifest) {
                return null;
            }

            $passengers = ManifestRoster::passengers($departure);

            if ($passengers->isEmpty()) {
                return null;
            }

            return $this->write(
                $departure,
                $kind,
                ManifestReason::First,
                $passengers,
                ManifestHash::of($kind, $passengers),
                null,
                true,
            );
        });

        return $manifest;
    }

    private function lock(Departure $departure): void
    {
        Departure::query()->whereKey($departure->id)->lockForUpdate()->first();
    }

    private function latest(Departure $departure, ManifestKind $kind): ?Manifest
    {
        $manifest = Manifest::query()
            ->where('departure_id', $departure->id)
            ->where('kind', $kind)
            ->orderByDesc('version')
            ->lockForUpdate()
            ->first();

        return $manifest instanceof Manifest ? $manifest : null;
    }

    /**
     * @param  Collection<int, ManifestPassenger>  $passengers
     */
    private function write(
        Departure $departure,
        ManifestKind $kind,
        ManifestReason $reason,
        Collection $passengers,
        string $hash,
        ?User $actor,
        bool $system,
    ): Manifest {
        $version = ((int) Manifest::query()
            ->where('departure_id', $departure->id)
            ->where('kind', $kind)
            ->max('version')) + 1;

        $due = ManifestDue::forDeparture($departure, $passengers);
        $paths = null;

        try {
            $paths = $this->files->store($departure, $kind, $version, $passengers, $due);
            $complete = $passengers->filter(fn (ManifestPassenger $passenger): bool => $passenger->complete())->count();

            $manifest = new Manifest([
                'departure_id' => $departure->id,
                'kind' => $kind,
                'version' => $version,
                'reason' => $reason,
                'generated_at' => now(),
                'generated_by' => $actor?->id,
                'passengers' => $passengers->count(),
                'complete' => $complete,
                'snapshot_hash' => $hash,
                'pdf_path' => $paths['pdf'],
                'csv_path' => $paths['csv'],
                'xlsx_path' => $paths['xlsx'],
            ]);
            $manifest->save();

            History::record($manifest, 'manifest.generated', after: [
                'kind' => $kind->value,
                'version' => $version,
                'reason' => $reason->value,
                'passengers' => $passengers->count(),
                'complete' => $complete,
            ], actor: $actor, system: $system, actorLabel: $system ? 'System · manifests' : null, extraContext: [
                'what' => $kind->label().' v'.$version.' issued',
            ]);

            return $manifest;
        } catch (Throwable $e) {
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (is_string($path)) {
                        Storage::disk('manifests')->delete($path);
                    }
                }
            }

            throw $e;
        }
    }
}

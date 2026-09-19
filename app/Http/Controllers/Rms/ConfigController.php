<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\ConfigKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rms\PublishConfigRequest;
use App\Http\Requests\Rms\ValidateConfigRequest;
use App\Http\Resources\Rms\ConfigCurrentResource;
use App\Http\Resources\Rms\ConfigValidationResource;
use App\Http\Resources\Rms\ConfigVersionDetailResource;
use App\Http\Resources\Rms\ConfigVersionSummaryResource;
use App\Models\User;
use App\Services\Config\ConfigPublisher;
use App\Services\Config\ConfigValidator;
use App\Services\Config\CurrentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class ConfigController extends Controller
{
    abstract protected function kind(): ConfigKind;

    protected function authorizeView(): void
    {
        $this->authorize('view', $this->kind()->modelClass());
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected function authorizePublish(array $document): void
    {
        $this->authorize('publish', $this->kind()->modelClass());
    }

    public function current(CurrentConfig $current): ConfigCurrentResource
    {
        $this->authorizeView();

        $version = $current->version($this->kind())->load('publisher');

        return new ConfigCurrentResource($version);
    }

    public function validateDocument(
        ValidateConfigRequest $request,
        ConfigValidator $validator,
    ): ConfigValidationResource {
        $this->authorizeView();

        /** @var array<string, mixed> $document */
        $document = $request->validated('document');

        return new ConfigValidationResource($validator->check($this->kind(), $document));
    }

    public function store(
        PublishConfigRequest $request,
        ConfigPublisher $publisher,
    ): JsonResponse {
        /** @var array<string, mixed> $document */
        $document = $request->validated('document');

        $this->authorizePublish($document);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        $approval = $request->validated('approval_reference');

        $version = $publisher->publish(
            $this->kind(),
            $document,
            (int) $request->validated('base_version'),
            is_string($approval) ? $approval : null,
            $actor,
        )->load('publisher');

        return (new ConfigVersionDetailResource($version))->response()->setStatusCode(201);
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorizeView();

        $modelClass = $this->kind()->modelClass();

        $versions = $modelClass::query()
            ->with('publisher')
            ->orderByDesc('version')
            ->paginate(25);

        return ConfigVersionSummaryResource::collection($versions);
    }

    public function show(int $version): ConfigVersionDetailResource
    {
        $this->authorizeView();

        $modelClass = $this->kind()->modelClass();
        $row = $modelClass::query()
            ->with('publisher')
            ->where('version', $version)
            ->firstOrFail();

        return new ConfigVersionDetailResource($row);
    }
}

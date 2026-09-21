<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexEngineActivityRequest;
use App\Http\Resources\Crm\EngineActivityItemResource;
use App\Support\Crm\CrmSync;
use App\Support\Crm\EngineActivity;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EngineActivityController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\EngineActivityItemResource>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, links: list<array{url: string|null, label: string, active: bool}>, path: string|null, per_page: int, to: int|null, total: int, kpis: array{events_today: int, identified: int, anonymous: int, inventory_touching: int, web_hold_minutes: int, web_hold_extension_minutes: int}}}',
    )]
    public function __invoke(IndexEngineActivityRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CrmSync::class);

        $validated = $request->validated();
        $filters = [];

        if (isset($validated['from'])) {
            $filters['from'] = $validated['from'];
        }

        if (isset($validated['to'])) {
            $filters['to'] = $validated['to'];
        }

        if (isset($validated['name'])) {
            $filters['name'] = $validated['name'];
        }

        if (array_key_exists('identified', $validated)) {
            $filters['identified'] = $request->boolean('identified');
        }

        $page = EngineActivity::page($filters, $request->integer('per_page', 50));

        return EngineActivityItemResource::collection($page['rows'])->additional([
            'meta' => [
                'kpis' => $page['kpis'],
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Services\Config\CurrentConfig;
use App\Support\BusinessRules\Registry;
use Illuminate\Http\Request;

class BusinessRulesCurrentResource extends ConfigCurrentResource
{
    /**
     * @return array{
     *     version: int,
     *     document: array<string, mixed>,
     *     published_at: string,
     *     published_by: array{id: int, name: string}|null,
     *     approval_reference: string|null,
     *     registry: list<array<string, mixed>>,
     *     counts: array{all: int, here: int, other_pages: int, locked: int, differs_or_flagged: int}
     * }
     */
    public function toArray(Request $request): array
    {
        $registry = Registry::rows(app(CurrentConfig::class));

        return [
            ...parent::toArray($request),
            'registry' => $registry,
            'counts' => Registry::counts($registry),
        ];
    }
}

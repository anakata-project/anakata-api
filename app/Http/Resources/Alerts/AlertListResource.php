<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertNotificationStatus;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: list<array{
 *         id: int,
 *         kind: AlertKind,
 *         kind_label: string,
 *         severity: AlertSeverity,
 *         title: string,
 *         sentence: string,
 *         state: 'open'|'acknowledged'|'resolved',
 *         raised_at: string|null,
 *         acknowledged_at: string|null,
 *         resolved_at: string|null,
 *         resolution: string|null,
 *         subject: array{type: string, id: int|null, reference: string, href: string},
 *         task: array{id: int, title: string, href: string}|null,
 *         may_acknowledge: bool,
 *         notifications: list<array{
 *             user_id: int,
 *             user_name: string,
 *             status: AlertNotificationStatus,
 *             error: string|null,
 *             sent_at: string|null,
 *             attempts: int
 *         }>
 *     }>,
 *     meta: array{
 *         current_page: int,
 *         last_page: int,
 *         per_page: int,
 *         total: int,
 *         counts: array{INFO: int, WARN: int, CRITICAL: int}
 *     }
 * } $resource
 */
#[SchemaName('AlertListResource')]
class AlertListResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  array{page: LengthAwarePaginator<int, Alert>, counts: array{INFO: int, WARN: int, CRITICAL: int}}  $resource
     */
    public function __construct(mixed $resource)
    {
        $page = $resource['page'];
        $data = [];

        foreach ($page->items() as $alert) {
            $data[] = (new AlertResource($alert))->toArray(request());
        }

        parent::__construct([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'counts' => $resource['counts'],
            ],
        ]);
    }

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         kind: AlertKind,
     *         kind_label: string,
     *         severity: AlertSeverity,
     *         title: string,
     *         sentence: string,
     *         state: 'open'|'acknowledged'|'resolved',
     *         raised_at: string|null,
     *         acknowledged_at: string|null,
     *         resolved_at: string|null,
     *         resolution: string|null,
     *         subject: array{type: string, id: int|null, reference: string, href: string},
     *         task: array{id: int, title: string, href: string}|null,
     *         may_acknowledge: bool,
     *         notifications: list<array{
     *             user_id: int,
     *             user_name: string,
     *             status: AlertNotificationStatus,
     *             error: string|null,
     *             sent_at: string|null,
     *             attempts: int
     *         }>
     *     }>,
     *     meta: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         counts: array{INFO: int, WARN: int, CRITICAL: int}
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

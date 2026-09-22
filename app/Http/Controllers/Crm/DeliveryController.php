<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Crm\DeliveryIndexResource;
use App\Models\Delivery;
use App\Support\Crm\DeliveryLog;
use Illuminate\Http\Request;

final class DeliveryController extends Controller
{
    public function index(Request $request): DeliveryIndexResource
    {
        $this->authorize('viewAny', Delivery::class);

        $result = DeliveryLog::page([
            'status' => self::queryString($request, 'status'),
            'kind' => self::queryString($request, 'kind'),
            'from' => self::queryString($request, 'from'),
            'to' => self::queryString($request, 'to'),
            'booking' => self::queryString($request, 'booking'),
            'contact' => self::queryString($request, 'contact'),
        ], $request->integer('per_page', 50));

        return new DeliveryIndexResource([
            'data' => collect($result['page']->items())->map(
                fn (Delivery $delivery): array => DeliveryLog::row($delivery),
            )->all(),
            'meta' => [
                'current_page' => $result['page']->currentPage(),
                'last_page' => $result['page']->lastPage(),
                'per_page' => $result['page']->perPage(),
                'total' => $result['page']->total(),
                'kpis' => $result['kpis'],
                'notes' => [
                    'engagement' => DeliveryLog::ENGAGEMENT,
                ],
            ],
        ]);
    }

    private static function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

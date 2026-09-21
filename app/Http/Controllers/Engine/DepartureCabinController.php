<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Resources\Engine\DepartureCabinResource;
use App\Models\Departure;
use App\Services\Engine\EngineCabins;
use App\Services\Engine\EngineFeed;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DepartureCabinController extends Controller
{
    #[DocumentedResponse(status: 200, type: 'list<App\\Http\\Resources\\Engine\\DepartureCabinResource>')]
    public function __invoke(Departure $departure, EngineFeed $feed, EngineCabins $cabins): JsonResponse
    {
        abort_unless($feed->isVisible($departure), Response::HTTP_NOT_FOUND);

        $rows = $cabins->for($departure);

        return response()->json(
            array_map(
                fn (array $row): array => (new DepartureCabinResource($row))->resolve(),
                $rows,
            ),
        )->header('Cache-Control', 'public, max-age=5');
    }
}

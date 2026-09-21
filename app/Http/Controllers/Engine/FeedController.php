<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Http\Resources\Engine\FeedResource;
use App\Services\Engine\EngineFeed;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FeedController extends Controller
{
    #[DocumentedResponse(status: 200, type: FeedResource::class)]
    #[DocumentedResponse(status: 304, description: 'Not modified')]
    public function __invoke(Request $request, EngineFeed $feed): Response
    {
        $payload = $feed->payload();
        $etag = $feed->etag($payload);

        $response = (new FeedResource($payload))
            ->toResponse($request)
            ->header('Cache-Control', 'public, max-age=15, stale-while-revalidate=15')
            ->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}

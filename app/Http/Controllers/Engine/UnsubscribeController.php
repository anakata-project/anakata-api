<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Engine\UnsubscribeContact;
use App\Http\Controllers\Controller;
use App\Http\Resources\Engine\UnsubscribeResource;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Request;

final class UnsubscribeController extends Controller
{
    public function __construct(private readonly UnsubscribeContact $unsubscribe) {}

    #[DocumentedResponse(status: 200, type: UnsubscribeResource::class)]
    public function show(string $token): UnsubscribeResource
    {
        return new UnsubscribeResource($this->unsubscribe->preview($token));
    }

    #[DocumentedResponse(status: 200, type: UnsubscribeResource::class)]
    public function store(Request $request, string $token): UnsubscribeResource
    {
        return new UnsubscribeResource($this->unsubscribe->withdraw($token, $request->ip()));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Rms\CountryResource;
use App\Support\Countries;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;

final class CountryController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'list<App\\Http\\Resources\\Rms\\CountryResource>',
    )]
    public function index(): JsonResponse
    {
        $rows = [];

        foreach (Countries::all() as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        return response()->json(
            CountryResource::collection($rows)->resolve(),
        );
    }
}

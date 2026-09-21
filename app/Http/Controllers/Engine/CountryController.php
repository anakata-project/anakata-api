<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Support\Countries;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;

final class CountryController extends Controller
{
    #[DocumentedResponse(status: 200, type: 'list<App\\Http\\Resources\\Engine\\EngineCountryResource>')]
    public function __invoke(): JsonResponse
    {
        $rows = array_map(
            fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
            array_keys(Countries::all()),
            array_values(Countries::all()),
        );

        return response()->json($rows);
    }
}

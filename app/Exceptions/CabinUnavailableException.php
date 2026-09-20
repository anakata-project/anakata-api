<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CabinUnavailableException extends HttpException
{
    /**
     * @param  list<array{cabin: array{id: int, code: string, label: string}, held_by: array{kind: string, holder_type: string, reference: string|null}}>  $unavailable
     */
    public function __construct(
        public array $unavailable,
        string $message = 'Cabin unavailable.',
    ) {
        parent::__construct(409, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'unavailable' => $this->unavailable,
        ], 409);
    }
}

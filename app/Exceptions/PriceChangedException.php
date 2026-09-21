<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Resources\Engine\EngineQuoteResource;
use App\Services\Pricing\ReservationQuote;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PriceChangedException extends HttpException
{
    public function __construct(
        public ReservationQuote $quote,
        string $message = 'The price changed. Review the new quote and submit again.',
    ) {
        parent::__construct(409, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'quote' => (new EngineQuoteResource($this->quote))->toArray(request()),
        ], 409);
    }
}

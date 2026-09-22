<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EmailConflictException extends HttpException
{
    public function __construct(
        public readonly int $contactId,
        public readonly string $contactName,
        string $message,
    ) {
        parent::__construct(409, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflicting_contact' => [
                'id' => $this->contactId,
                'name' => $this->contactName,
            ],
        ], 409);
    }
}

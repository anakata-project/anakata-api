<?php

declare(strict_types=1);

namespace App\Ai\Tools\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

trait ReturnsToolJson
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function success(array $payload): string
    {
        return $this->encode(['success' => true, ...$payload]);
    }

    protected function failure(string $error): string
    {
        return $this->encode([
            'success' => false,
            'error' => $error,
        ]);
    }

    protected function fromException(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            return $this->failure(is_string($message) ? $message : 'That request is not valid.');
        }

        if ($exception instanceof AuthorizationException) {
            return $this->failure('You do not have permission to do that.');
        }

        if ($exception instanceof HttpException) {
            $message = $exception->getMessage();

            return $this->failure($message !== '' ? $message : 'That action was refused.');
        }

        throw $exception;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

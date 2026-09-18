<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SensitiveFields;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class GuardCrmSensitiveData
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $payload = $this->jsonPayload($response);
        if ($payload === null) {
            return $response;
        }

        $hits = SensitiveFields::keysIn($payload);
        if ($hits === []) {
            return $response;
        }

        if (app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'CRM response contained sensitive fields: '.implode(', ', $hits),
            );
        }

        Log::warning('CRM response contained sensitive fields', ['fields' => $hits]);

        $stripped = SensitiveFields::strip($payload);

        if ($response instanceof JsonResponse) {
            $response->setData($stripped);

            return $response;
        }

        $response->setContent(json_encode($stripped, JSON_THROW_ON_ERROR));

        return $response;
    }

    /**
     * @return array<mixed>|null
     */
    private function jsonPayload(Response $response): ?array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return is_array($data) ? $data : null;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'application/json')) {
            return null;
        }

        $decoded = json_decode($response->getContent() ?: '', true);

        return is_array($decoded) ? $decoded : null;
    }
}

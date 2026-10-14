<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Support\Ai\CalendarPhrase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ResolveCalendar implements Tool
{
    use ReturnsToolJson;

    public function description(): Stringable|string
    {
        return 'Convert a relative date phrase (today, tomorrow, next Monday, in 2 weeks, 15 January, mañana) '
            .'into a Galápagos civil date YYYY-MM-DD. Pass the operator\'s exact words. Never compute a date yourself.';
    }

    public function handle(Request $request): Stringable|string
    {
        $phrase = trim((string) ($request['phrase'] ?? ''));
        $date = CalendarPhrase::resolve($phrase);

        if ($date === null) {
            return $this->failure('Could not resolve that date. Ask for an exact YYYY-MM-DD.');
        }

        return $this->success([
            'phrase' => $phrase,
            'date' => $date,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'phrase' => $schema->string()
                ->description('The date phrase to resolve')
                ->required(),
        ];
    }
}

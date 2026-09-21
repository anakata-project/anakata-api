<?php

declare(strict_types=1);

namespace App\Support\History;

use App\Models\ChangeHistory;
use App\Models\User;
use App\Support\SensitiveFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class History
{
    public static int $baseTransactionLevel = 0;

    /** @var list<string> */
    private const DIFF_EXCLUDED = [
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'remember_token',
        'password',
    ];

    /** @var list<string> */
    private const ALWAYS_REDACTED = [
        'password',
        'remember_token',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $extraContext
     */
    public static function record(
        Model $subject,
        string $event,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?User $actor = null,
        array $extraContext = [],
        bool $system = false,
    ): ChangeHistory {
        self::guardTransaction();

        if ($system) {
            $actor = null;
        } else {
            $actor ??= Auth::user();
            $actor = $actor instanceof User ? $actor : null;
        }

        $entry = new ChangeHistory([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => self::subjectLabel($subject),
            'event' => $event,
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'actor_label' => $actor instanceof User ? $actor->name : 'System',
            'before' => self::redact($before),
            'after' => self::redact($after),
            'reason' => $reason,
            'context' => self::redact([
                'source' => self::source(),
                'ip' => request()->ip(),
                'request_id' => request()->header('X-Request-Id') ?: (string) Str::uuid(),
                ...$extraContext,
            ]) ?? [],
        ]);

        $entry->save();

        return $entry;
    }

    /**
     * Build a before/after pair from a model that has just been saved.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(Model $model): array
    {
        $after = [];
        $before = [];
        $previous = $model->getPrevious();

        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, self::DIFF_EXCLUDED, true)) {
                continue;
            }

            $after[$key] = $value;
            $before[$key] = $previous[$key] ?? null;
        }

        return [$before, $after];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = array_values(array_unique([
            ...SensitiveFields::all(),
            ...self::ALWAYS_REDACTED,
        ]));

        return self::redactRecursive($payload, $sensitive);
    }

    /**
     * @param  array<mixed>  $payload
     * @param  list<string>  $sensitive
     * @return array<mixed>
     */
    private static function redactRecursive(array $payload, array $sensitive): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, $sensitive, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value)
                ? self::redactRecursive($value, $sensitive)
                : $value;
        }

        return $redacted;
    }

    private static function guardTransaction(): void
    {
        if (DB::transactionLevel() > self::$baseTransactionLevel) {
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            throw new RuntimeException('History must be written inside a transaction.');
        }

        Log::warning('History::record() was called outside a database transaction.');
    }

    private static function subjectLabel(Model $subject): ?string
    {
        if (method_exists($subject, 'historyLabel')) {
            $label = $subject->historyLabel();

            return is_string($label) && $label !== '' ? $label : null;
        }

        foreach (['reference', 'name'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function source(): string
    {
        $segments = explode('/', request()->path());

        if ($segments[0] !== 'api') {
            return 'system';
        }

        return match ($segments[1] ?? null) {
            'rms' => 'rms',
            'crm' => 'crm',
            'engine' => 'engine',
            'auth' => 'auth',
            default => 'system',
        };
    }
}

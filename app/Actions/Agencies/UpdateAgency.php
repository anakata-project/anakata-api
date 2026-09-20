<?php

declare(strict_types=1);

namespace App\Actions\Agencies;

use App\Actions\Action;
use App\Models\Agency;
use App\Models\User;
use App\Support\History\History;

final class UpdateAgency extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Agency $agency, array $data, User $actor): Agency
    {
        return $this->transaction(function () use ($agency, $data, $actor): Agency {
            $fields = ['name', 'contact', 'network', 'payment_terms', 'commission_pct'];
            $before = [];
            $after = [];

            foreach ($fields as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $current = $agency->getAttribute($field);
                $next = $data[$field];

                if ($field === 'commission_pct') {
                    $current = (int) $current;
                    $next = (int) $next;
                }

                if ($current === $next) {
                    continue;
                }

                $before[$field] = $current;
                $after[$field] = $next;
                $agency->setAttribute($field, $next);
            }

            if ($after === []) {
                return $agency;
            }

            $agency->save();

            History::record($agency, 'agency.updated', before: $before, after: $after, actor: $actor);

            return $agency->fresh(['users', 'decidedBy']) ?? $agency;
        });
    }
}

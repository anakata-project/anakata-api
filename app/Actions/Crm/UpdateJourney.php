<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Models\Journey;
use App\Models\User;
use App\Support\History\History;

final class UpdateJourney extends Action
{
    public function handle(Journey $journey, bool $active, User $actor): Journey
    {
        return $this->transaction(function () use ($journey, $active, $actor): Journey {
            if ($journey->active === $active) {
                return $journey;
            }

            $before = $journey->active;
            $journey->active = $active;
            $journey->save();

            History::record(
                $journey,
                'journey.updated',
                before: ['active' => $before],
                after: ['active' => $active],
                actor: $actor,
            );

            return $journey->fresh() ?? $journey;
        });
    }
}

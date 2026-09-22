<?php

declare(strict_types=1);

namespace App\Actions\GuestExperience;

use App\Actions\Action;
use App\Models\Departure;
use App\Models\User;
use App\Support\History\History;

final class RecordBriefPrinted extends Action
{
    public function handle(Departure $departure, User $actor, string $format): void
    {
        $this->transaction(function () use ($departure, $actor, $format): void {
            History::record($departure, 'brief.printed', after: [
                'format' => $format,
            ], actor: $actor);
        });
    }
}

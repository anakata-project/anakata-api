<?php

declare(strict_types=1);

namespace App\Actions\Alerts;

use App\Actions\Action;
use App\Models\Alert;
use App\Models\User;
use App\Support\Alerts\AlertRegistry;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AcknowledgeAlert extends Action
{
    public function handle(Alert $alert, User $actor): Alert
    {
        if (! AlertRegistry::sees($actor, $alert->kind)) {
            throw new HttpException(403, 'You cannot acknowledge this alert.');
        }

        if ($alert->resolved_at !== null) {
            throw new HttpException(422, 'This alert is already resolved.');
        }

        if ($alert->acknowledged_at !== null) {
            throw new HttpException(422, 'This alert is already acknowledged.');
        }

        return $this->transaction(function () use ($alert, $actor): Alert {
            $alert->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->id,
            ])->save();

            History::record($alert, 'alert.acknowledged', after: [
                'acknowledged_by' => $actor->id,
            ], actor: $actor);

            return $alert->refresh();
        });
    }
}

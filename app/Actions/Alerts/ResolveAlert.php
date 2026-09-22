<?php

declare(strict_types=1);

namespace App\Actions\Alerts;

use App\Actions\Action;
use App\Models\Alert;
use App\Support\History\History;

final class ResolveAlert extends Action
{
    public function handle(Alert $alert, string $fact): Alert
    {
        if ($alert->resolved_at !== null) {
            return $alert;
        }

        return $this->transaction(function () use ($alert, $fact): Alert {
            $alert->forceFill([
                'resolved_at' => now(),
                'resolution' => $fact,
            ])->save();

            History::record($alert, 'alert.resolved', after: [
                'fact' => $fact,
            ], system: true);

            return $alert->refresh();
        });
    }
}

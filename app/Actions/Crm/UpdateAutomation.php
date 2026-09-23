<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Models\AutomationSetting;
use App\Models\User;
use App\Support\Automations\AutomationCatalogue;
use App\Support\Automations\AutomationDefinition;
use App\Support\History\History;

final class UpdateAutomation extends Action
{
    public function handle(AutomationDefinition $definition, bool $enabled, string $reason, User $actor): AutomationSetting
    {
        if (! $definition->built || ! $definition->switchable) {
            abort(422, AutomationCatalogue::REFUSAL);
        }

        /** @var AutomationSetting $setting */
        $setting = $this->transaction(function () use ($definition, $enabled, $reason, $actor): AutomationSetting {
            $setting = AutomationSetting::query()->firstOrNew(['key' => $definition->key]);
            $before = ['enabled' => $setting->exists ? (bool) $setting->enabled : true];

            $setting->enabled = $enabled;

            if ($enabled) {
                $setting->disabled_reason = null;
                $setting->disabled_by = null;
                $setting->disabled_at = null;
            } else {
                $setting->disabled_reason = $reason;
                $setting->disabled_by = $actor->id;
                $setting->disabled_at = now();
            }

            $setting->save();

            History::record(
                $setting,
                $enabled ? 'automation.enabled' : 'automation.disabled',
                before: $before,
                after: ['enabled' => $enabled],
                reason: $reason,
                actor: $actor,
            );

            return $setting;
        });

        return $setting;
    }
}

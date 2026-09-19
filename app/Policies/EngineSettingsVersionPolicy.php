<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Config\Documents\EngineSettingsDocument;
use Illuminate\Auth\Access\Response;

final class EngineSettingsVersionPolicy extends ConfigPolicy
{
    protected function viewPermission(): Permission
    {
        return Permission::PanelRms;
    }

    protected function publishPermission(): Permission
    {
        return Permission::EngineSettingsManage;
    }

    /**
     * @param  list<string>  $changedPaths
     */
    public function publish(User $actor, array $changedPaths = []): Response
    {
        $ruleLabels = [];

        foreach ($changedPaths as $path) {
            if (EngineSettingsDocument::isCopyPath($path)) {
                continue;
            }

            $ruleLabels[] = EngineSettingsDocument::labels()[$path] ?? $path;
        }

        if ($ruleLabels === []) {
            if ($actor->hasPermission(Permission::EngineCopyManage)
                || $actor->hasPermission(Permission::EngineSettingsManage)
            ) {
                return Response::allow();
            }

            return Response::deny('This user cannot publish engine settings.');
        }

        if ($actor->hasPermission(Permission::EngineSettingsManage)) {
            return Response::allow();
        }

        return Response::deny(
            'This change includes rule fields ('.implode(', ', $ruleLabels).'). Only users who can edit engine rules can publish it.',
        );
    }
}

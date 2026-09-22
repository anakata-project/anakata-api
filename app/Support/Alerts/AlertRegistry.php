<?php

declare(strict_types=1);

namespace App\Support\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Enums\Permission;
use App\Models\User;

final class AlertRegistry
{
    /**
     * @return list<AlertKindDefinition>
     */
    public static function all(): array
    {
        return [
            new AlertKindDefinition(
                AlertKind::OverdueBalance,
                AlertSeverity::Warn,
                [Permission::BookingsOverdueDecision],
                'The overdue flag becomes true (day 1).',
                'The overdue flag clears, or the due date changes.',
                'rms',
            ),
            new AlertKindDefinition(
                AlertKind::CommissionCap,
                AlertSeverity::Warn,
                [Permission::CommissionsOverrideCap],
                'A booking is held at ON_HOLD_AGENCY.',
                'The booking leaves ON_HOLD_AGENCY.',
                'rms',
            ),
            new AlertKindDefinition(
                AlertKind::WireNotReceived,
                AlertSeverity::Warn,
                [Permission::PaymentsMarkWireReceived],
                'An awaiting-wire payment passes its window.',
                'The wire is received or released.',
                'rms',
            ),
            new AlertKindDefinition(
                AlertKind::SlaBreach,
                AlertSeverity::Warn,
                [Permission::RecordsActOnAny],
                'An open system task passes its due time.',
                'The task closes, or its due time is no longer past.',
                'crm',
            ),
            new AlertKindDefinition(
                AlertKind::DeliveryFailed,
                AlertSeverity::Warn,
                [Permission::SyncRetry],
                'A delivery is failed or blocked.',
                'A later delivery of the same document is sent.',
                'crm',
            ),
        ];
    }

    public static function get(AlertKind $kind): AlertKindDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->kind === $kind) {
                return $definition;
            }
        }

        throw new \LogicException('Missing alert kind '.$kind->value);
    }

    public static function sees(User $user, AlertKind $kind): bool
    {
        foreach (self::get($kind)->audience as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AlertKind>
     */
    public static function visibleTo(User $user): array
    {
        $kinds = [];

        foreach (self::all() as $definition) {
            if (self::sees($user, $definition->kind)) {
                $kinds[] = $definition->kind;
            }
        }

        return $kinds;
    }
}

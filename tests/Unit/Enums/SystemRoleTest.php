<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;

test('manager default permissions match the sprint list', function (): void {
    expect(SystemRole::Manager->defaultPermissions()->map->value->all())->toBe([
        Permission::PanelRms->value,
        Permission::PanelCrm->value,
        Permission::BookingsViewAll->value,
        Permission::BookingsCreate->value,
        Permission::BookingsChangeStatus->value,
        Permission::BookingsMove->value,
        Permission::RequestsConfirm->value,
        Permission::RequestsRelease->value,
        Permission::DeparturesManage->value,
        Permission::ItinerariesManage->value,
        Permission::BlocksManage->value,
        Permission::OffersManage->value,
        Permission::AgenciesManage->value,
        Permission::EngineCopyManage->value,
        Permission::PipelineMoveStage->value,
        Permission::ContactsManage->value,
        Permission::GuestsViewSensitive->value,
    ]);
});

test('sales exec default permissions match the sprint list', function (): void {
    expect(SystemRole::SalesExec->defaultPermissions()->map->value->all())->toBe([
        Permission::PanelRms->value,
        Permission::PanelCrm->value,
        Permission::BookingsViewAll->value,
        Permission::BookingsCreate->value,
        Permission::BookingsChangeStatus->value,
        Permission::BookingsMove->value,
        Permission::RequestsConfirm->value,
        Permission::RequestsRelease->value,
        Permission::PipelineMoveStage->value,
        Permission::ContactsManage->value,
    ]);
});

test('admin default permissions are empty because they are never read', function (): void {
    expect(SystemRole::Admin->defaultPermissions())->toBeEmpty();
});

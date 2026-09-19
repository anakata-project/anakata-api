<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Http\TestConfigController;
use Tests\Support\Config\TestConfigPolicy;
use Tests\Support\Config\TestConfigVersion;

trait ConfiguresTestConfig
{
    protected function setUpTestConfig(): void
    {
        Relation::morphMap([
            'test_config_version' => TestConfigVersion::class,
        ]);

        Gate::policy(TestConfigVersion::class, TestConfigPolicy::class);

        Route::middleware(['api', 'auth:sanctum', 'active', 'permission:panel.rms'])
            ->prefix('api/rms/test-config')
            ->group(function (): void {
                Route::get('/', [TestConfigController::class, 'current']);
                Route::post('validate', [TestConfigController::class, 'validateDocument']);
                Route::post('versions', [TestConfigController::class, 'store']);
                Route::get('versions', [TestConfigController::class, 'index']);
                Route::get('versions/{version}', [TestConfigController::class, 'show'])
                    ->whereNumber('version');
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('engine', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown');
        });

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }
}

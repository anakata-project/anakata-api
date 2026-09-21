<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\SensitiveFields;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * @return list<string>
     */
    public static function hiddenRequestParameters(): array
    {
        return [
            '_token',
            'password',
            'password_confirmation',
            'remember_token',
            ...SensitiveFields::all(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function hiddenRequestHeaders(): array
    {
        return [
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(fn (IncomingEntry $entry): bool => $this->app->environment('local'));
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(self::hiddenRequestParameters());
        Telescope::hideRequestHeaders(self::hiddenRequestHeaders());
        Telescope::hideResponseParameters(SensitiveFields::all());
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user = null): bool {
            return $user !== null && in_array($user->email, self::allowedEmails(), true);
        });
    }

    /**
     * Emails allowed to open Telescope outside local.
     *
     * @return list<string>
     */
    private static function allowedEmails(): array
    {
        return [];
    }
}

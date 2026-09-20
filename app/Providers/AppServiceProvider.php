<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ConfigKind;
use App\Enums\Permission;
use App\Events\ConfigPublished;
use App\Listeners\ClearCurrentConfigCache;
use App\Models\Booking;
use App\Models\BusinessRuleVersion;
use App\Models\Contact;
use App\Models\Departure;
use App\Models\EngineSettingsVersion;
use App\Models\Group;
use App\Models\InternalBlock;
use App\Models\Itinerary;
use App\Models\RateVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Config\ConfigRegistry;
use App\Services\Config\CurrentConfig;
use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\Config\Documents\EngineSettingsDocument;
use App\Support\Config\Documents\RatesDocument;
use App\Support\Iso;
use DateTimeInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentConfig::class);
        $this->app->singleton(ConfigRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('engine', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('auth-email', function (Request $request): Limit {
            return Limit::perMinute(6)->by($request->ip() ?? 'unknown');
        });

        Password::defaults(function (): Password {
            $rule = Password::min(8);

            return App::isProduction() ? $rule->uncompromised() : $rule;
        });

        ResetPassword::createUrlUsing(function (User $notifiable, string $token): string {
            return rtrim((string) config('anakata.panel_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'rate_version' => RateVersion::class,
            'business_rule_version' => BusinessRuleVersion::class,
            'engine_settings_version' => EngineSettingsVersion::class,
            'itinerary' => Itinerary::class,
            'departure' => Departure::class,
            'internal_block' => InternalBlock::class,
            'contact' => Contact::class,
            'group' => Group::class,
            'booking' => Booking::class,
        ]);

        $this->app->make(ConfigRegistry::class)->register(
            ConfigKind::Rates,
            RateVersion::class,
            RatesDocument::class,
            RatesDocument::initial(),
        );

        $this->app->make(ConfigRegistry::class)->register(
            ConfigKind::EngineSettings,
            EngineSettingsVersion::class,
            EngineSettingsDocument::class,
            EngineSettingsDocument::initial(),
        );

        $this->app->make(ConfigRegistry::class)->register(
            ConfigKind::BusinessRules,
            BusinessRuleVersion::class,
            BusinessRulesDocument::class,
            BusinessRulesDocument::initial(),
        );

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }

        Date::serializeUsing(fn (DateTimeInterface $date): string => Iso::utc($date));

        Event::listen(ConfigPublished::class, ClearCurrentConfigCache::class);

        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/database/migrations'));
        }
    }
}

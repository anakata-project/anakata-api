<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ConfigKind;
use App\Enums\Permission;
use App\Events\ConfigPublished;
use App\Events\HoldExpired;
use App\Listeners\ClearCurrentConfigCache;
use App\Listeners\MarkRequestHoldExpired;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\BusinessRuleVersion;
use App\Models\Consent;
use App\Models\Contact;
use App\Models\Departure;
use App\Models\EngineSettingsVersion;
use App\Models\ExtraVersion;
use App\Models\Group;
use App\Models\Guest;
use App\Models\InternalBlock;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\RateVersion;
use App\Models\RefundRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Config\ConfigRegistry;
use App\Services\Config\CurrentConfig;
use App\Services\Stripe\FakeStripeGateway;
use App\Services\Stripe\StripeGateway;
use App\Services\Stripe\StripeSdkGateway;
use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\Config\Documents\EngineSettingsDocument;
use App\Support\Config\Documents\ExtrasDocument;
use App\Support\Config\Documents\RatesDocument;
use App\Support\Iso;
use App\Support\Stripe\StripeGatewayBinding;
use DateTimeInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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

        $this->app->singleton(FakeStripeGateway::class);
        $this->app->singleton(StripeSdkGateway::class);
        $this->app->singleton(StripeGateway::class, function (Application $app): StripeGateway {
            if (StripeGatewayBinding::usesFake($app)) {
                $fake = $app->make(FakeStripeGateway::class);
                if ($app->environment('local')) {
                    $fake->includeFileFixture = true;
                }

                return $fake;
            }

            return $app->make(StripeSdkGateway::class);
        });
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

        RateLimiter::for('stripe-webhook', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip() ?? 'stripe');
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
            'extra_version' => ExtraVersion::class,
            'itinerary' => Itinerary::class,
            'departure' => Departure::class,
            'internal_block' => InternalBlock::class,
            'contact' => Contact::class,
            'group' => Group::class,
            'agency' => Agency::class,
            'booking' => Booking::class,
            'guest' => Guest::class,
            'consent' => Consent::class,
            'booking_request' => BookingRequest::class,
            'waitlist_entry' => WaitlistEntry::class,
            'payment' => Payment::class,
            'payment_link' => PaymentLink::class,
            'refund_request' => RefundRequest::class,
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

        $this->app->make(ConfigRegistry::class)->register(
            ConfigKind::Extras,
            ExtraVersion::class,
            ExtrasDocument::class,
            ExtrasDocument::initial(),
        );

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }

        Date::serializeUsing(fn (DateTimeInterface $date): string => Iso::utc($date));

        Event::listen(ConfigPublished::class, ClearCurrentConfigCache::class);
        Event::listen(HoldExpired::class, MarkRequestHoldExpired::class);

        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/database/migrations'));
        }
    }
}

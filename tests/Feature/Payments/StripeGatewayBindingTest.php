<?php

declare(strict_types=1);

use App\Services\Stripe\FakeStripeGateway;
use App\Services\Stripe\StripeGateway;
use App\Services\Stripe\StripeSdkGateway;
use App\Support\Stripe\StripeGatewayBinding;

test('testing with empty keys uses FakeStripeGateway', function (): void {
    expect(StripeGatewayBinding::usesFake())->toBeTrue();
    expect(app(StripeGateway::class))->toBeInstanceOf(FakeStripeGateway::class);
});

test('production with empty keys still resolves StripeSdkGateway', function (): void {
    $previous = $this->app['env'];
    $this->app['env'] = 'production';
    config(['services.stripe.secret' => '']);
    $this->app->forgetInstance(StripeGateway::class);

    try {
        expect(StripeGatewayBinding::usesFake())->toBeFalse();
        expect(app(StripeGateway::class))->toBeInstanceOf(StripeSdkGateway::class);
    } finally {
        $this->app['env'] = $previous;
        $this->app->forgetInstance(StripeGateway::class);
    }
});

test('local with a secret uses StripeSdkGateway', function (): void {
    $previous = $this->app['env'];
    $this->app['env'] = 'local';
    config(['services.stripe.secret' => 'sk_test_real']);
    $this->app->forgetInstance(StripeGateway::class);

    try {
        expect(StripeGatewayBinding::usesFake())->toBeFalse();
        expect(app(StripeGateway::class))->toBeInstanceOf(StripeSdkGateway::class);
    } finally {
        $this->app['env'] = $previous;
        config(['services.stripe.secret' => '']);
        $this->app->forgetInstance(StripeGateway::class);
    }
});

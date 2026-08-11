<?php

use App\Actions\GetPaymentSetupStatus;

it('requires complete gateway configuration before reporting online payments as ready', function (): void {
    config()->set([
        'services.payments.driver' => 'stripe',
        'services.stripe.secret' => 'stripe-secret',
        'services.stripe.publishable_key' => 'stripe-publishable',
        'services.stripe.endpoint' => 'https://stripe.example.test',
        'services.stripe.webhook_secret' => null,
        'services.whish.enabled' => true,
        'services.whish.channel' => 'whish-channel',
        'services.whish.secret' => 'whish-secret',
        'services.whish.website_url' => null,
    ]);

    $status = app(GetPaymentSetupStatus::class)->handle();

    expect($status['cash']['ready'])->toBeTrue()
        ->and($status['stripe']['status'])->toBe('not_configured')
        ->and($status['whish']['status'])->toBe('not_configured');

    config()->set([
        'services.stripe.webhook_secret' => 'stripe-webhook-secret',
        'services.whish.website_url' => 'https://isp.example.test',
    ]);

    $status = app(GetPaymentSetupStatus::class)->handle();

    expect($status['stripe']['status'])->toBe('configured')
        ->and($status['whish']['status'])->toBe('configured');
});

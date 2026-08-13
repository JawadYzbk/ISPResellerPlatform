<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;

uses(RefreshDatabase::class);

it('probes every enabled provider without creating side effects', function (): void {
    config()->set([
        'services.frankfurter.enabled' => true,
        'services.frankfurter.quotes' => ['EUR'],
        'services.payments.driver' => 'stripe',
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.publishable_key' => 'pk_test_123',
        'services.stripe.webhook_secret' => 'whsec_123',
        'services.stripe.endpoint' => 'https://api.stripe.test',
        'services.whish.enabled' => true,
        'services.whish.channel' => 'channel',
        'services.whish.secret' => 'secret',
        'services.whish.website_url' => 'https://app.example.test',
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web.test:3001',
        'services.whatsapp.web.token' => 'bridge-token',
    ]);
    Http::fake([
        'https://api.frankfurter.dev/*' => Http::response([
            ['date' => '2026-08-13', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.92],
        ]),
        'https://api.stripe.test/*' => Http::response(['id' => 'acct_test'], 200),
        'http://whatsapp-web.test:3001/health' => Http::response(['ok' => true], 200),
    ]);
    app()->instance(WhishHttpTransport::class, new class implements WhishHttpTransport
    {
        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            return new WhishHttpResponse(200, '{"status":true,"data":{"USD":"100"}}');
        }
    });

    $this->artisan('platform:provider-check')
        ->assertSuccessful()
        ->expectsOutputToContain('Frankfurter returned a live USD quote.')
        ->expectsOutputToContain('Whish account endpoint accepted the configured credentials.')
        ->expectsOutputToContain('WhatsApp Web.js bridge health endpoint is reachable.');

    Http::assertSentCount(3);
});

it('fails when an enabled provider is not reachable', function (): void {
    config()->set([
        'services.frankfurter.enabled' => true,
        'services.frankfurter.quotes' => ['EUR'],
        'services.payments.driver' => 'null',
        'services.whish.enabled' => false,
        'services.whatsapp.mode' => 'cloud',
    ]);
    Http::fake(['https://api.frankfurter.dev/*' => Http::response([], 503)]);

    expect(Artisan::call('platform:provider-check', ['--json' => true]))
        ->toBe(1)
        ->and(Artisan::output())
        ->toContain('"status": "failed"')
        ->toContain('Frankfurter could not be reached or returned an invalid response.');
});

it('does not probe disabled providers', function (): void {
    config()->set([
        'services.frankfurter.enabled' => false,
        'services.payments.driver' => 'null',
        'services.whish.enabled' => false,
        'services.whatsapp.mode' => 'cloud',
    ]);
    Http::fake();

    $this->artisan('platform:provider-check')
        ->assertSuccessful()
        ->expectsOutputToContain('Frankfurter synchronization is disabled.')
        ->expectsOutputToContain('Stripe is not the selected online payment driver.')
        ->expectsOutputToContain('Whish Pay is disabled.')
        ->expectsOutputToContain('WhatsApp Web.js is not the selected provider.');

    Http::assertNothingSent();
});

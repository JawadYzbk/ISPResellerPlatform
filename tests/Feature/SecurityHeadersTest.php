<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds baseline browser security headers to web responses', function (): void {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(self), microphone=()');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('http://127.0.0.1:5173')
        ->toContain('ws://127.0.0.1:5173')
        ->not->toContain('http://[::1]:5173');
});

it('does not expose development script capabilities in production CSP', function (): void {
    config(['app.env' => 'production']);

    $policy = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($policy)
        ->not->toContain("'unsafe-eval'")
        ->not->toContain('http://127.0.0.1:5173')
        ->not->toContain('ws://127.0.0.1:5173');
});

it('allows the configured Reverb websocket origin', function (): void {
    config([
        'app.env' => 'production',
        'broadcasting.connections.reverb.options' => ['host' => 'realtime.example.test', 'port' => 443, 'scheme' => 'https'],
    ]);

    $policy = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($policy)->toContain('wss://realtime.example.test:443');
});

it('allows Stripe.js only when the Stripe payment driver is enabled', function (): void {
    config(['services.payments.driver' => 'stripe']);

    $policy = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain('https://js.stripe.com')
        ->toContain('https://api.stripe.com')
        ->toContain('frame-src');
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds baseline browser security headers to web responses', function (): void {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('http://127.0.0.1:5173')
        ->toContain('ws://127.0.0.1:5173')
        ->toContain('http://[::1]:5173');
});

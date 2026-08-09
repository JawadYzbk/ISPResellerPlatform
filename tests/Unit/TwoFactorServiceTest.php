<?php

use App\Models\User;
use App\Security\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OTPHP\TOTP;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('enrolls, confirms, and verifies a TOTP device', function (): void {
    $user = User::factory()->create();
    $service = app(TwoFactorService::class);
    $setup = $service->begin($user);

    expect($setup['provisioning_uri'])->toStartWith('otpauth://totp/');

    $code = TOTP::createFromSecret($setup['secret'])->now();

    expect($service->confirm($user, $code))->toBeTrue()
        ->and($service->enabled($user->refresh()))->toBeTrue()
        ->and($service->verify($user->refresh(), $code))->toBeTrue();
});

it('consumes a recovery code once', function (): void {
    $user = User::factory()->create();
    $service = app(TwoFactorService::class);
    $setup = $service->begin($user);
    $recoveryCode = $setup['recovery_codes'][0];

    expect($service->confirm($user, '000000'))->toBeFalse()
        ->and($service->verify($user->refresh(), $recoveryCode))->toBeFalse();

    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    expect($service->verify($user->refresh(), $recoveryCode))->toBeTrue()
        ->and($service->verify($user->refresh(), $recoveryCode))->toBeFalse();
});

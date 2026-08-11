<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('sends a generic reset response without revealing whether an account exists', function (): void {
    Notification::fake();
    $submit = fn (array $data) => $this->withSession(['_token' => 'test-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-token')
        ->post(route('password.email'), $data);

    $submit(['email' => 'missing@example.test'])
        ->assertRedirect()
        ->assertSessionHas('success', 'If an account matches that address, a reset link is on its way.');

    Notification::assertNothingSent();

    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => Hash::make('old-password'),
        'role' => 'tenant_owner',
    ]);

    $submit(['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('success', 'If an account matches that address, a reset link is on its way.');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password once and invalidates the token', function (): void {
    $submit = fn (array $data) => $this->withSession(['_token' => 'test-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-token')
        ->post(route('password.update'), $data);
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operator',
        'email' => 'operator@example.test',
        'password' => Hash::make('old-password'),
        'role' => 'operator',
    ]);
    $token = Password::broker()->createToken($user);

    $submit([
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue()
        ->and(Password::broker()->tokenExists($user->refresh(), $token))->toBeFalse();
});

it('rejects short passwords and invalid reset tokens', function (): void {
    $submit = fn (array $data) => $this->withSession(['_token' => 'test-token'])
        ->withHeader('X-CSRF-TOKEN', 'test-token')
        ->post(route('password.update'), $data);
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operator',
        'email' => 'operator@example.test',
        'password' => Hash::make('old-password'),
        'role' => 'operator',
    ]);
    $token = Password::broker()->createToken($user);

    $submit([
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $submit([
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');
});

<?php

use App\Actions\ChangeUserLocale;
use App\Actions\ListSessionDevices;
use App\Actions\RevokeSessionDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists sessions and revokes only another device', function (): void {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'current-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Browser', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'old-session', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Mobile', 'payload' => '', 'last_activity' => now()->subHour()->timestamp],
    ]);

    expect(app(ListSessionDevices::class)->handle($user, 'current-session'))->toHaveCount(2)
        ->and(app(RevokeSessionDevice::class)->handle($user, 'current-session', 'current-session'))->toBeFalse()
        ->and(app(RevokeSessionDevice::class)->handle($user, 'old-session', 'current-session'))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);
});

it('supports the supported locale switch', function (): void {
    $user = User::factory()->create(['locale' => 'en']);

    expect(app(ChangeUserLocale::class)->handle($user, 'ar')->locale)->toBe('ar');
});

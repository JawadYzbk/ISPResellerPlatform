<?php

use App\Actions\GetBackupHealth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

it('reports a safe warning when the local destination has no archive yet', function (): void {
    Storage::fake('local');

    $health = app(GetBackupHealth::class)->handle();

    expect($health['status'])->toBe('WARN')
        ->and($health['destinations'])->toHaveCount(1)
        ->and($health['destinations'][0]['reachable'])->toBeTrue()
        ->and($health['destinations'][0]['backup_count'])->toBe(0)
        ->and($health['destinations'][0]['failures'][0]['message'])->toBe('No recent verified backup archive was found.');
});

it('treats a missing backup as a production readiness failure', function (): void {
    Config::set('app.env', 'production');
    Storage::fake('local');

    expect(app(GetBackupHealth::class)->handle()['status'])->toBe('FAIL');
});

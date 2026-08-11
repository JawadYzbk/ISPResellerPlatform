<?php

use App\Support\SentryPrivacyScrubber;
use Sentry\Event;

it('keeps telemetry privacy-safe and backup destinations explicitly configured', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('backup.backup.destination.disks'))->toBe(['local'])
        ->and(config('backup.backup.verify_backup'))->toBeTrue()
        ->and(config('backup.backup.encryption'))->toBe('default')
        ->and(config('broadcasting.connections.reverb.driver'))->toBe('reverb')
        ->and(config('reverb.apps.apps.0.allowed_origins'))->not->toContain('*')
        ->and(config('sentry.before_send'))->toBe([SentryPrivacyScrubber::class, 'scrub']);

    $event = Event::createEvent()->setRequest([
        'url' => 'https://example.test/health',
        'data' => ['email' => 'subscriber@example.test'],
        'cookies' => ['session' => 'secret'],
        'headers' => ['authorization' => 'Bearer secret'],
    ]);
    $scrub = config('sentry.before_send');
    $scrubbed = $scrub($event);

    expect($scrubbed)->toBe($event)
        ->and($scrubbed->getUser())->toBeNull()
        ->and($scrubbed->getRequest())->not->toHaveKey('data')
        ->and($scrubbed->getRequest())->not->toHaveKey('cookies')
        ->and($scrubbed->getRequest())->not->toHaveKey('headers');
});

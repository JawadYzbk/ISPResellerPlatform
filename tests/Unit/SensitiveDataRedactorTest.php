<?php

use App\Logging\SensitiveDataRedactor;

it('redacts credentials recursively without changing safe context', function (): void {
    $redacted = SensitiveDataRedactor::redact([
        'tenant_id' => 7,
        'password' => 'do-not-log',
        'router' => ['radius_secret' => 'also-private', 'identity' => 'router-a'],
    ]);

    expect($redacted)->toBe([
        'tenant_id' => 7,
        'password' => '[REDACTED]',
        'router' => ['radius_secret' => '[REDACTED]', 'identity' => 'router-a'],
    ]);
});

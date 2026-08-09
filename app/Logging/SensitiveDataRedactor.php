<?php

namespace App\Logging;

final class SensitiveDataRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'password_encrypted', 'secret', 'secret_encrypted', 'token', 'authorization',
        'radius_secret', 'client_secret', 'app_key', 'api_key', 'pin', 'ppp_password',
    ];

    public static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = self::redact($childValue, is_string($childKey) ? $childKey : null);
            }

            return $redacted;
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}

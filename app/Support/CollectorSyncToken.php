<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CollectorSyncToken
{
    public function issue(int $tenantId, int $userId, CarbonImmutable $asOf): string
    {
        $payload = json_encode([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'as_of' => $asOf->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, $this->key());

        return $this->encode($payload.'.'.$signature);
    }

    public function read(string $token, int $tenantId, int $userId): CarbonImmutable
    {
        $signed = $this->decode($token);
        [$payload, $signature] = array_pad(explode('.', $signed, 2), 2, null);
        if (! is_string($signature) || ! hash_equals(hash_hmac('sha256', $payload, $this->key()), $signature)) {
            throw new InvalidArgumentException('The sync token is invalid.');
        }

        try {
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('The sync token is invalid.');
        }

        if (($data['tenant_id'] ?? null) !== $tenantId || ($data['user_id'] ?? null) !== $userId || ! is_string($data['as_of'] ?? null)) {
            throw new InvalidArgumentException('The sync token is invalid.');
        }

        try {
            return CarbonImmutable::parse($data['as_of']);
        } catch (\Throwable) {
            throw new InvalidArgumentException('The sync token is invalid.');
        }
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('The sync token is invalid.');
        }

        return $decoded;
    }
}

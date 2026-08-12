<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\IpPool;
use InvalidArgumentException;

final readonly class UpdateIpPool implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(IpPool $pool, array $data): IpPool
    {
        if (filled($data['gateway'] ?? null) && $this->version((string) $data['gateway']) !== $pool->version) {
            throw new InvalidArgumentException('The gateway must use the same IP version as the pool.');
        }

        $pool->update([
            'name' => trim((string) $data['name']),
            'gateway' => filled($data['gateway'] ?? null) ? trim((string) $data['gateway']) : null,
            'type' => $data['type'],
            'router_id' => $data['router_id'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return $pool->refresh();
    }

    private function version(string $address): ?int
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return 4;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return 6;
        }

        return null;
    }
}

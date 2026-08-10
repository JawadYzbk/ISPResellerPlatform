<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\IpPool;
use InvalidArgumentException;

final readonly class CreateIpPool implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): IpPool
    {
        $cidr = strtolower(trim((string) $data['cidr']));
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        $version = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 6 : 4;
        $maximumPrefix = $version === 6 ? 128 : 32;
        if ($network === null || $prefix === null || filter_var($network, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix) || (int) $prefix > $maximumPrefix) {
            throw new InvalidArgumentException('The CIDR range is invalid.');
        }
        if ((int) $data['version'] !== $version) {
            throw new InvalidArgumentException('The selected IP version does not match the CIDR range.');
        }
        if (filled($data['gateway'] ?? null) && $this->version((string) $data['gateway']) !== $version) {
            throw new InvalidArgumentException('The gateway must use the same IP version as the pool.');
        }

        return IpPool::create([
            'router_id' => $data['router_id'] ?? null,
            'name' => $data['name'],
            'cidr' => $cidr,
            'gateway' => filled($data['gateway'] ?? null) ? $data['gateway'] : null,
            'type' => $data['type'],
            'version' => $version,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
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

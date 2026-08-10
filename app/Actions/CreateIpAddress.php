<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\IpAddress;
use App\Models\IpPool;
use DomainException;

final readonly class CreateIpAddress implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(IpPool $pool, array $data): IpAddress
    {
        $address = trim((string) $data['address']);
        $flag = $pool->version === 6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
        if (filter_var($address, FILTER_VALIDATE_IP, $flag) === false) {
            throw new DomainException('The address does not match the pool IP version.');
        }
        if (IpAddress::query()->where('ip_pool_id', $pool->id)->where('address', $address)->exists()) {
            throw new DomainException('This address is already recorded in the selected pool.');
        }

        return IpAddress::create([
            'ip_pool_id' => $pool->id,
            'address' => $address,
            'status' => $data['status'] ?? 'free',
        ]);
    }
}

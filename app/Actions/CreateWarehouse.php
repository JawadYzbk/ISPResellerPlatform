<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use DomainException;

final readonly class CreateWarehouse implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Warehouse
    {
        $assignedUserId = isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null;
        if ($data['type'] === 'warehouse') {
            $assignedUserId = null;
        } elseif ($assignedUserId === null || ! User::query()->whereKey($assignedUserId)->where('tenant_id', app(Tenancy::class)->requireId())->exists()) {
            throw new DomainException('Assign a field stock location to a workspace user.');
        }

        return Warehouse::create([
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'type' => $data['type'],
            'assigned_user_id' => $assignedUserId,
            'is_active' => true,
        ]);
    }
}

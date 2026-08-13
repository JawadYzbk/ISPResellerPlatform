<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWarehouse implements Action
{
    /** @param array{name: string, code: string, type: string, assigned_user_id?: int|null, is_active: bool} $data */
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $locked = Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);
            $assignedUserId = isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null;
            if ($data['type'] === 'warehouse') {
                $assignedUserId = null;
            } elseif ($assignedUserId === null || ! User::query()->whereKey($assignedUserId)->where('tenant_id', $locked->tenant_id)->exists()) {
                throw new DomainException('Assign a field stock location to a workspace user.');
            }
            $changesCustodian = (int) $locked->assigned_user_id !== (int) $assignedUserId || $locked->type !== $data['type'];
            $deactivates = $locked->is_active && ! $data['is_active'];
            if (($changesCustodian || $deactivates) && ($locked->units()->whereIn('status', ['available', 'returned'])->exists()
                || $locked->stockBalances()->where('quantity', '>', 0)->exists())) {
                throw new DomainException('Transfer all available stock before changing or deactivating this custodian location.');
            }
            $locked->forceFill([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'type' => $data['type'],
                'assigned_user_id' => $assignedUserId,
                'is_active' => (bool) $data['is_active'],
            ])->save();

            return $locked->refresh();
        });
    }
}

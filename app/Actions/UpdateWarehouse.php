<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWarehouse implements Action
{
    /** @param array{name: string, code: string, type: string, is_active: bool} $data */
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $locked = Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);
            $locked->forceFill([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'type' => $data['type'],
                'is_active' => (bool) $data['is_active'],
            ])->save();

            return $locked->refresh();
        });
    }
}

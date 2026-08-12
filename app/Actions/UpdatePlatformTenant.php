<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePlatformTenant implements Action
{
    /** @param array{name: string, status: string} $data */
    public function handle(Tenant $tenant, array $data, User $actor, RecordPlatformAudit $audit): Tenant
    {
        return DB::transaction(function () use ($tenant, $data, $actor, $audit): Tenant {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $before = ['name' => $locked->name, 'status' => $locked->status];
            $locked->forceFill($data)->save();

            $audit->handle($actor, 'Tenant updated.', $locked, [
                'before' => $before,
                'after' => ['name' => $locked->name, 'status' => $locked->status],
            ]);

            return $locked->loadCount(['users', 'customers', 'services']);
        });
    }
}

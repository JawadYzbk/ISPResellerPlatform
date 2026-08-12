<?php

namespace App\Actions;

use App\Authorization\PermissionCatalog;
use App\Contracts\Action;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final readonly class CreateTenant implements Action
{
    /** @param array{name: string, slug: string, locale: string, timezone: string, base_currency: string, collection_currency: string, owner_name: string, owner_email: string, owner_password: string} $data */
    public function handle(array $data, User $actor, RecordPlatformAudit $audit): Tenant
    {
        return DB::transaction(function () use ($data, $actor, $audit): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => 'active',
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'base_currency' => $data['base_currency'],
                'collection_currency' => $data['collection_currency'],
            ]);
            $owner = $tenant->users()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'role' => 'tenant_owner',
                'locale' => null,
                'email_verified_at' => now(),
            ]);

            app(Tenancy::class)->run($tenant, function () use ($owner): void {
                foreach (PermissionCatalog::all() as $permission) {
                    Permission::findOrCreate($permission, 'web');
                }

                $role = Role::findOrCreate('tenant_owner', 'web');
                $role->syncPermissions(PermissionCatalog::all());
                $owner->syncRoles([$role]);
            });

            $audit->handle(
                $actor,
                'Tenant created.',
                $tenant,
                ['owner_email' => $owner->email, 'slug' => $tenant->slug],
            );

            return $tenant->loadCount(['users', 'customers', 'services']);
        });
    }
}

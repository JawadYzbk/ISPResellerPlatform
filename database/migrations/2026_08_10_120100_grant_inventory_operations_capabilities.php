<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['inventory.view', 'inventory.assign'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table('roles')->where('name', 'operations_manager')->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['inventory.view', 'inventory.assign'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table('roles')->where('name', 'operations_manager')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->whereIn('role_id', $roleIds)->delete();
    }
};

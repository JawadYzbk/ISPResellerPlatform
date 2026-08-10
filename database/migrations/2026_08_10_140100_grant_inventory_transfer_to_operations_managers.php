<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'inventory.transfer')->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->where('name', 'operations_manager')->pluck('id');
        foreach ($roleIds as $roleId) {
            if ($permissionId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'inventory.transfer')->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->where('name', 'operations_manager')->pluck('id');
        if ($permissionId !== null) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->whereIn('role_id', $roleIds)->delete();
        }
    }
};

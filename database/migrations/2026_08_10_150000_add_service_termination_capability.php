<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'services.terminate')->where('guard_name', 'web')->value('id');
        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'services.terminate',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('name', ['platform_operator', 'tenant_owner', 'operations_manager'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'services.terminate')->where('guard_name', 'web')->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};

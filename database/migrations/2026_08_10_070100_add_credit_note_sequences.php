<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->get(['id', 'timezone']) as $tenant) {
            $branch = DB::table('branches')->where('tenant_id', $tenant->id)->where('is_default', true)->first();
            if ($branch === null) {
                continue;
            }

            DB::table('document_sequences')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'key' => 'credit_note',
                'period' => CarbonImmutable::now($tenant->timezone ?: 'UTC')->format('Y'),
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('document_sequences')->where('key', 'credit_note')->delete();
    }
};

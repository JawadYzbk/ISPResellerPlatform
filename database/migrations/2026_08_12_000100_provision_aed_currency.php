<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $tenant): void {
                $values = [
                    'name' => 'United Arab Emirates Dirham',
                    'decimal_digits' => 2,
                    'is_active' => true,
                    'updated_at' => now(),
                ];
                $query = DB::table('currencies')->where('tenant_id', $tenant->id)->where('code', 'AED');

                if ($query->exists()) {
                    $query->update($values);

                    return;
                }

                DB::table('currencies')->insert([
                    'tenant_id' => $tenant->id,
                    'code' => 'AED',
                    ...$values,
                    'created_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Keep the currency row in place so a rollback cannot invalidate AED transactions.
    }
};

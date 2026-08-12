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
                DB::table('currencies')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'code' => 'AED'],
                    [
                        'name' => 'United Arab Emirates Dirham',
                        'decimal_digits' => 2,
                        'is_active' => true,
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        // Keep the currency row in place so a rollback cannot invalidate AED transactions.
    }
};

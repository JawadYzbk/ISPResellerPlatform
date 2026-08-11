<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->select(['id', 'base_currency', 'collection_currency'])
            ->orderBy('id')
            ->get()
            ->each(function (object $tenant): void {
                DB::table('currencies')
                    ->where('tenant_id', $tenant->id)
                    ->update(['is_base' => false, 'is_collection' => false, 'updated_at' => now()]);

                $definitions = [
                    strtoupper((string) $tenant->base_currency) => ['is_base' => true, 'is_collection' => false],
                    strtoupper((string) $tenant->collection_currency) => ['is_base' => false, 'is_collection' => true],
                ];
                if (strtoupper((string) $tenant->base_currency) === strtoupper((string) $tenant->collection_currency)) {
                    $definitions[strtoupper((string) $tenant->base_currency)] = ['is_base' => true, 'is_collection' => true];
                }

                foreach ($definitions as $code => $flags) {
                    $values = [
                        'name' => $code,
                        'decimal_digits' => $code === 'LBP' ? 0 : 2,
                        ...$flags,
                        'is_active' => true,
                        'updated_at' => now(),
                    ];
                    $exists = DB::table('currencies')
                        ->where('tenant_id', $tenant->id)
                        ->where('code', $code)
                        ->exists();

                    if ($exists) {
                        DB::table('currencies')
                            ->where('tenant_id', $tenant->id)
                            ->where('code', $code)
                            ->update($values);
                    } else {
                        DB::table('currencies')->insert([
                            'tenant_id' => $tenant->id,
                            'code' => $code,
                            ...$values,
                            'created_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Currency role reconciliation is intentionally not reversed.
    }
};

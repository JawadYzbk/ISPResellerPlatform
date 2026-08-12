<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')->nullable()->after('notes')->constrained('journal_entries')->restrictOnDelete();
            $table->unique('journal_entry_id');
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')->nullable()->after('actor_id')->constrained('journal_entries')->restrictOnDelete();
            $table->unique('journal_entry_id');
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ([
                ['code' => '2220', 'name' => 'Supplier Payable', 'category' => 'liability', 'normal_balance' => 'credit'],
                ['code' => '5200', 'name' => 'Supplier Cost', 'category' => 'expense', 'normal_balance' => 'debit'],
            ] as $account) {
                DB::table('ledger_accounts')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => $account['code']],
                    [...$account, 'is_system' => true, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropUnique(['journal_entry_id']);
            $table->dropConstrainedForeignId('journal_entry_id');
        });

        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->dropUnique(['journal_entry_id']);
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};

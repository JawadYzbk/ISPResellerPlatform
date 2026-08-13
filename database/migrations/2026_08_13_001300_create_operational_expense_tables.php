<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('expense_vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('operational_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('collector_custody_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('payment_source', 16);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->text('description');
            $table->string('reference', 120)->nullable();
            $table->timestamp('incurred_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'status', 'incurred_at'], 'operational_expense_status_idx');
            $table->index(['tenant_id', 'collector_id', 'status'], 'operational_expense_collector_idx');
        });

        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->foreignId('operational_expense_id')->nullable()->after('collector_task_message_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'operational_expense_id'], 'media_operational_expense_idx');
        });

        $now = now();
        $tenantIds = DB::table('tenants')->pluck('id');
        foreach ($tenantIds as $tenantId) {
            foreach ([
                ['code' => '1010', 'name' => 'Bank', 'category' => 'asset', 'normal_balance' => 'debit'],
                ['code' => '5300', 'name' => 'Operating Expenses', 'category' => 'expense', 'normal_balance' => 'debit'],
            ] as $account) {
                DB::table('ledger_accounts')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    ...$account,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $expenseAccountId = DB::table('ledger_accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', '5300')
                ->value('id');
            DB::table('expense_categories')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'public_id' => (string) Str::ulid(),
                'ledger_account_id' => $expenseAccountId,
                'name' => 'General operations',
                'code' => 'GENERAL',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissions = ['expenses.view', 'expenses.create', 'expenses.approve', 'expenses.manage'];
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rolePermissions = [
            'admin' => $permissions,
            'platform_operator' => $permissions,
            'tenant_owner' => $permissions,
            'operations_manager' => $permissions,
            'billing_manager' => $permissions,
            'cashier' => ['expenses.view', 'expenses.create'],
            'collector' => ['expenses.view', 'expenses.create'],
            'auditor' => ['expenses.view'],
        ];
        foreach ($rolePermissions as $roleName => $assignedPermissions) {
            $roleIds = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('name', $assignedPermissions)->where('guard_name', 'web')->pluck('id');
            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropIndex('media_operational_expense_idx');
            $table->dropConstrainedForeignId('operational_expense_id');
        });
        Schema::dropIfExists('operational_expenses');
        Schema::dropIfExists('expense_vendors');
        Schema::dropIfExists('expense_categories');

        $permissionIds = DB::table('permissions')->whereIn('name', ['expenses.view', 'expenses.create', 'expenses.approve', 'expenses.manage'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('ledger_accounts')->whereIn('code', ['1010', '5300'])->delete();
    }
};

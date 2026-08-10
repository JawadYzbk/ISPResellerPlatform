<?php

namespace Database\Seeders;

use App\Authorization\PermissionCatalog;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CapabilitySeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'platform_operator' => PermissionCatalog::ALL,
        'tenant_owner' => PermissionCatalog::ALL,
        'operations_manager' => [
            'customers.view', 'customers.create', 'customers.update', 'customers.export',
            'services.view', 'services.create', 'services.activate', 'services.suspend', 'services.change_plan',
            'plans.manage',
            'tickets.view', 'tickets.create', 'tickets.assign', 'tickets.close', 'workorders.complete',
            'reports.operations', 'reports.export', 'users.manage', 'audit.view',
        ],
        'billing_manager' => [
            'customers.view', 'services.view', 'billing.invoices.view', 'billing.invoices.issue',
            'billing.adjustments.create', 'payments.collect', 'payments.backdate', 'payments.void',
            'refunds.approve', 'wallets.view', 'settlements.approve', 'reports.finance', 'reports.export',
        ],
        'cashier' => ['customers.view', 'services.view', 'billing.invoices.view', 'payments.collect', 'reports.finance'],
        'collector' => ['customers.view', 'services.view', 'payments.collect', 'tickets.view'],
        'support_agent' => ['customers.view', 'services.view', 'network.view', 'tickets.view', 'tickets.create', 'tickets.close'],
        'technician' => ['customers.view', 'services.view', 'network.view', 'inventory.view', 'inventory.assign', 'tickets.view', 'tickets.assign', 'workorders.complete'],
        'network_administrator' => [
            'customers.view', 'services.view', 'services.activate', 'services.suspend', 'services.force_resume',
            'network.view', 'network.provision', 'network.disconnect', 'network.credentials.reveal',
        ],
        'reseller_owner' => [
            'customers.view', 'customers.create', 'customers.update', 'services.view', 'services.create',
            'services.activate', 'services.suspend', 'services.change_plan', 'billing.invoices.view',
            'payments.collect', 'wallets.view', 'wallets.fund', 'tickets.view', 'tickets.create', 'reports.operations',
        ],
        'reseller_staff' => ['customers.view', 'customers.create', 'customers.update', 'services.view', 'tickets.view', 'tickets.create'],
        'auditor' => ['customers.view', 'services.view', 'billing.invoices.view', 'wallets.view', 'network.view', 'tickets.view', 'reports.finance', 'reports.operations', 'reports.export', 'audit.view'],
        'customer' => ['services.view', 'billing.invoices.view', 'payments.collect', 'tickets.view', 'tickets.create'],
    ];

    public function run(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Tenant::query()->each(function (Tenant $tenant): void {
            app(Tenancy::class)->run($tenant, function (): void {
                foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                    $role = Role::findOrCreate($roleName, 'web');
                    $role->syncPermissions($permissions);
                }
            });
        });
    }
}

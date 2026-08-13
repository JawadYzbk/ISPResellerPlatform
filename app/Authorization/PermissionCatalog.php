<?php

namespace App\Authorization;

use InvalidArgumentException;

final class PermissionCatalog
{
    /** @var list<string> */
    public const ALL = [
        'customers.view', 'customers.create', 'customers.update', 'customers.export', 'customers.anonymize',
        'services.view', 'services.create', 'services.activate', 'services.suspend', 'services.pause', 'services.terminate',
        'services.change_plan', 'services.force_resume',
        'plans.manage',
        'billing.invoices.view', 'billing.invoices.issue', 'billing.adjustments.create',
        'payments.collect', 'payments.backdate', 'payments.void', 'refunds.approve',
        'partners.manage', 'wallets.view', 'wallets.fund', 'settlements.approve',
        'network.view', 'network.provision', 'network.disconnect', 'network.credentials.reveal',
        'suppliers.view', 'suppliers.manage', 'credentials.import', 'credentials.reserve', 'credentials.assign', 'credentials.reveal',
        'inventory.view', 'inventory.receive', 'inventory.transfer', 'inventory.assign', 'inventory.write_off',
        'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.manage',
        'workorders.complete',
        'tickets.view', 'tickets.create', 'tickets.assign', 'tickets.close',
        'reports.finance', 'reports.operations', 'reports.export',
        'settings.manage', 'users.manage', 'roles.manage', 'audit.view',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function assertKnown(string $permission): void
    {
        if (! in_array($permission, self::ALL, true)) {
            throw new InvalidArgumentException("Unknown permission [{$permission}].");
        }
    }
}

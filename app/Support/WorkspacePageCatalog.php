<?php

namespace App\Support;

use App\Models\User;

final class WorkspacePageCatalog
{
    /** @var list<array{label: string, detail: string, href: string, permission?: string|list<string>, role?: string, defaultable: bool}> */
    private const PAGES = [
        ['label' => 'Overview', 'detail' => 'Workspace', 'href' => '/dashboard', 'defaultable' => true],
        ['label' => 'Customers', 'detail' => 'Workspace', 'href' => '/customers', 'permission' => 'customers.view', 'defaultable' => true],
        ['label' => 'Plans', 'detail' => 'Workspace', 'href' => '/plans', 'permission' => 'plans.manage', 'defaultable' => false],
        ['label' => 'Services', 'detail' => 'Workspace', 'href' => '/services', 'permission' => 'services.view', 'defaultable' => true],
        ['label' => 'Billing', 'detail' => 'Billing', 'href' => '/billing/invoices', 'permission' => 'billing.invoices.view', 'defaultable' => true],
        ['label' => 'Credit notes', 'detail' => 'Billing', 'href' => '/billing/credit-notes', 'permission' => 'billing.invoices.view', 'defaultable' => false],
        ['label' => 'Payments', 'detail' => 'Billing', 'href' => '/billing/payments', 'permission' => 'payments.collect', 'defaultable' => true],
        ['label' => 'Cash shifts', 'detail' => 'Billing', 'href' => '/billing/shifts', 'permission' => 'payments.collect', 'defaultable' => false],
        ['label' => 'FX rates', 'detail' => 'Billing', 'href' => '/billing/exchange-rates', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Expenses', 'detail' => 'Billing', 'href' => '/operations/expenses', 'permission' => 'expenses.view', 'defaultable' => false],
        ['label' => 'Collector desk', 'detail' => 'Field operations', 'href' => '/field', 'permission' => ['customers.view', 'payments.collect'], 'role' => 'collector', 'defaultable' => true],
        ['label' => 'Collector check-ins', 'detail' => 'Field operations', 'href' => '/operations/collector-check-ins', 'permission' => 'reports.operations', 'defaultable' => false],
        ['label' => 'Collector routes', 'detail' => 'Field operations', 'href' => '/operations/collector-routes', 'permission' => 'reports.operations', 'defaultable' => false],
        ['label' => 'Collector tasks', 'detail' => 'Field operations', 'href' => '/operations/collector-tasks', 'permission' => 'reports.operations', 'defaultable' => false],
        ['label' => 'Collector custody', 'detail' => 'Field operations', 'href' => '/operations/collector-custody', 'permission' => 'reports.operations', 'defaultable' => false],
        ['label' => 'Tickets', 'detail' => 'Support & work', 'href' => '/operations/tickets', 'permission' => 'tickets.view', 'defaultable' => false],
        ['label' => 'Work orders', 'detail' => 'Support & work', 'href' => '/operations/work-orders', 'permission' => 'workorders.complete', 'defaultable' => true],
        ['label' => 'Work-order calendar', 'detail' => 'Support & work', 'href' => '/operations/work-orders/calendar', 'permission' => 'workorders.complete', 'defaultable' => false],
        ['label' => 'Buildings & boxes', 'detail' => 'Network', 'href' => '/operations/topology/buildings', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Optical access', 'detail' => 'Network', 'href' => '/operations/optical', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Live sessions', 'detail' => 'Network', 'href' => '/operations/sessions', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Incidents', 'detail' => 'Network', 'href' => '/operations/incidents', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Network queue', 'detail' => 'Network', 'href' => '/operations/network-commands', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Routers', 'detail' => 'Network', 'href' => '/operations/routers', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'POPs', 'detail' => 'Network', 'href' => '/operations/pops', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'IP pools', 'detail' => 'Network', 'href' => '/operations/ip-pools', 'permission' => 'network.view', 'defaultable' => false],
        ['label' => 'Inventory', 'detail' => 'Inventory & partners', 'href' => '/operations/inventory', 'permission' => 'inventory.view', 'defaultable' => true],
        ['label' => 'Imports', 'detail' => 'Inventory & partners', 'href' => '/operations/imports', 'permission' => ['customers.create', 'plans.manage', 'services.create', 'inventory.receive', 'billing.adjustments.create', 'network.view'], 'defaultable' => false],
        ['label' => 'Credentials', 'detail' => 'Inventory & partners', 'href' => '/operations/credentials', 'permission' => 'suppliers.view', 'defaultable' => false],
        ['label' => 'Suppliers', 'detail' => 'Inventory & partners', 'href' => '/operations/suppliers', 'permission' => 'suppliers.view', 'defaultable' => false],
        ['label' => 'Partners', 'detail' => 'Inventory & partners', 'href' => '/partners/commercial', 'permission' => 'wallets.view', 'defaultable' => false],
        ['label' => 'Reports', 'detail' => 'Insights', 'href' => '/reports/operations', 'permission' => 'reports.operations', 'defaultable' => true],
        ['label' => 'Settings', 'detail' => 'Settings', 'href' => '/settings/general', 'permission' => 'settings.manage', 'defaultable' => true],
        ['label' => 'Integrations', 'detail' => 'Settings', 'href' => '/settings/integrations', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Pilot readiness', 'detail' => 'Settings', 'href' => '/settings/readiness', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Users and invitations', 'detail' => 'Settings', 'href' => '/settings/users', 'permission' => 'users.manage', 'defaultable' => false],
        ['label' => 'Collector territories', 'detail' => 'Settings', 'href' => '/settings/collector-territories', 'permission' => 'users.manage', 'defaultable' => false],
        ['label' => 'Branches and zones', 'detail' => 'Settings', 'href' => '/settings/locations', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'WhatsApp setup', 'detail' => 'Settings', 'href' => '/settings/whatsapp', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Ticket responses', 'detail' => 'Settings', 'href' => '/settings/ticket-responses', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Notification templates', 'detail' => 'Settings', 'href' => '/settings/notification-templates', 'permission' => 'settings.manage', 'defaultable' => false],
        ['label' => 'Profile', 'detail' => 'Account', 'href' => '/profile', 'defaultable' => false],
        ['label' => 'Notifications', 'detail' => 'Account', 'href' => '/notifications', 'defaultable' => false],
        ['label' => 'Active sessions', 'detail' => 'Account', 'href' => '/security/sessions', 'defaultable' => false],
    ];

    /** @return list<array{label: string, detail: string, href: string, permission?: string|list<string>, role?: string, defaultable: bool}> */
    public function all(): array
    {
        return self::PAGES;
    }

    /** @return list<array{label: string, detail: string, href: string, permission?: string|list<string>, role?: string, defaultable: bool}> */
    public function availableFor(User $user): array
    {
        return array_values(array_filter(self::PAGES, fn (array $page): bool => $this->authorized($user, $page)));
    }

    /** @return list<array{label: string, detail: string, href: string, permission?: string|list<string>, role?: string, defaultable: bool}> */
    public function defaultViewsFor(User $user): array
    {
        return array_values(array_filter($this->availableFor($user), fn (array $page): bool => $page['defaultable']));
    }

    public function defaultDestination(User $user): string
    {
        if ($user->isPlatformOperator()) {
            return route('admin.tenants');
        }

        $defaultView = (string) ($user->default_view ?: '/dashboard');
        $available = array_column($this->defaultViewsFor($user), 'href');

        return url(in_array($defaultView, $available, true) ? $defaultView : '/dashboard');
    }

    /** @param array{permission?: string|list<string>, role?: string} $page */
    private function authorized(User $user, array $page): bool
    {
        if (isset($page['role']) && $page['role'] !== $user->role) {
            return false;
        }

        $permission = $page['permission'] ?? null;
        if ($permission === null) {
            return true;
        }

        if (is_array($permission)) {
            return collect($permission)->contains(fn (string $capability): bool => $user->can($capability));
        }

        return $user->can($permission);
    }
}

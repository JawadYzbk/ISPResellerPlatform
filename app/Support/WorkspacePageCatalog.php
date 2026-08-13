<?php

namespace App\Support;

use App\Models\User;

final class WorkspacePageCatalog
{
    /** @var array<string, list<string>> */
    private const SEARCH_ALIASES = [
        'Overview' => ['نظرة عامة', 'Vue d’ensemble'],
        'Customers' => ['العملاء', 'Clients'],
        'Plans' => ['الباقات', 'Forfaits'],
        'Services' => ['الخدمات', 'Services'],
        'Billing' => ['الفوترة', 'Facturation'],
        'Credit notes' => ['إشعارات الدائن', 'Avoirs'],
        'Payments' => ['الدفعات', 'Paiements'],
        'Cash shifts' => ['ورديات النقد', 'Caisses'],
        'FX rates' => ['أسعار الصرف', 'Taux de change'],
        'Expenses' => ['المصروفات', 'Dépenses'],
        'Collector desk' => ['مكتب المحصل', 'Bureau du collecteur'],
        'Collector check-ins' => ['تسجيلات حضور المحصلين', 'Présences des collecteurs'],
        'Collector routes' => ['مسارات المحصلين', 'Tournées des collecteurs'],
        'Collector tasks' => ['مهام المحصلين', 'Tâches des collecteurs'],
        'Collector custody' => ['عهدة المحصل', 'Caisse du collecteur'],
        'Tickets' => ['التذاكر', 'Tickets'],
        'Work orders' => ['أوامر العمل', 'Ordres de travail'],
        'Work-order calendar' => ['تقويم أوامر العمل', 'Calendrier des ordres de travail'],
        'Buildings & boxes' => ['المباني والصناديق', 'Bâtiments et boîtiers'],
        'Optical access' => ['الوصول البصري', 'Accès optique'],
        'Live sessions' => ['الجلسات المباشرة', 'Sessions en direct'],
        'Incidents' => ['الحوادث', 'Incidents'],
        'Network queue' => ['قائمة الشبكة', 'File réseau'],
        'Routers' => ['الموجهات', 'Routeurs'],
        'POPs' => ['نقاط الحضور', 'Points de présence'],
        'IP pools' => ['مجموعات عناوين IP', 'Pools IP'],
        'Inventory' => ['المخزون', 'Stock'],
        'Imports' => ['الاستيراد', 'Imports'],
        'Credentials' => ['بيانات الاعتماد', 'Identifiants'],
        'Suppliers' => ['الموردون', 'Fournisseurs'],
        'Partners' => ['الشركاء', 'Partenaires'],
        'Reports' => ['التقارير', 'Rapports'],
        'Settings' => ['الإعدادات', 'Paramètres'],
        'Integrations' => ['التكاملات', 'Intégrations'],
        'Pilot readiness' => ['جاهزية التشغيل', 'Préparation au pilote'],
        'Users and invitations' => ['المستخدمون والدعوات', 'Utilisateurs et invitations'],
        'Collector territories' => ['مناطق المحصلين', 'Territoires des collecteurs'],
        'Branches and zones' => ['الفروع والمناطق', 'Agences et zones'],
        'WhatsApp setup' => ['إعداد واتساب', 'Configuration WhatsApp'],
        'Ticket responses' => ['ردود التذاكر', 'Réponses des tickets'],
        'Notification templates' => ['قوالب الإشعارات', 'Modèles de notification'],
        'Profile' => ['الملف الشخصي', 'Profil'],
        'Notifications' => ['الإشعارات', 'Notifications'],
        'Active sessions' => ['الجلسات النشطة', 'Sessions actives'],
        'Workspace' => ['مساحة العمل', 'Espace de travail'],
        'Field operations' => ['العمليات الميدانية', 'Opérations terrain'],
        'Support & work' => ['الدعم والعمل', 'Support et interventions'],
        'Network' => ['الشبكة', 'Réseau'],
        'Inventory & partners' => ['المخزون والشركاء', 'Stock et partenaires'],
        'Insights' => ['التحليلات', 'Analyses'],
        'Account' => ['الحساب', 'Compte'],
    ];

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

    /** @param array{label: string, detail: string, href: string} $page */
    public function matchesSearch(array $page, string $needle): bool
    {
        $terms = array_merge(
            [$page['label'], $page['detail'], $page['href']],
            self::SEARCH_ALIASES[$page['label']] ?? [],
            self::SEARCH_ALIASES[$page['detail']] ?? [],
        );

        return str_contains(mb_strtolower(implode(' ', $terms)), mb_strtolower($needle));
    }

    public function defaultDestination(User $user): string
    {
        if ($user->isPlatformOperator()) {
            return route('admin.tenants');
        }

        $defaultView = (string) ($user->default_view ?: '/dashboard');
        $defaultable = array_column(
            array_filter(self::PAGES, fn (array $page): bool => $page['defaultable']),
            'href',
        );

        return url(in_array($defaultView, $defaultable, true) ? $defaultView : '/dashboard');
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

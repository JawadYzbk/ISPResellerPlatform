import { Form, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Banknote,
    Bell,
    Building2,
    CalendarDays,
    CircleAlert,
    ChevronDown,
    ClipboardList,
    ClipboardCheck,
    CreditCard,
    FileUp,
    FilePlus2,
    HandCoins,
    KeyRound,
    Command,
    LayoutDashboard,
    LogOut,
    MessageSquare,
    MapPinned,
    Navigation,
    Network,
    Package,
    Radio,
    ReceiptText,
    Scale,
    Store,
    Search,
    Router,
    Tags,
    UserRound,
    Users,
    Wifi,
    WalletCards,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type PropsWithChildren } from 'react';

import RealtimeBridge from '@/components/RealtimeBridge';
import OfflineBanner from '@/components/OfflineBanner';
import { Toaster } from '@/components/ui/toaster';
import { createTranslator, roleLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type NavigationItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    permission?: string | string[];
};

type NavigationGroup = {
    label: string;
    items: NavigationItem[];
};

type SearchResult = { type: string; label: string; detail: string; href: string; localized?: boolean };

export default function AppLayout({ children }: PropsWithChildren) {
    const page = usePage<PageProps>();
    const { auth, app } = page.props;
    const { url } = page;
    const t = createTranslator(app.locale);
    const isPlatformOperator = auth.isPlatformOperator;
    const [searchOpen, setSearchOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [accountOpen, setAccountOpen] = useState(false);
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});
    const searchInput = useRef<HTMLInputElement>(null);
    const accountMenu = useRef<HTMLDivElement>(null);
    const can = (permission: string | string[]) =>
        Array.isArray(permission)
            ? permission.some((item) => auth.permissions.includes(item))
            : auth.permissions.includes(permission);
    const canUseCollectorDesk =
        auth.user?.role === 'collector' && can('customers.view') && can('payments.collect');
    const pathname = url.split('?')[0].replace(/\/+$/, '') || '/';
    const matchesPath = (href: string) => pathname === href || pathname.startsWith(`${href}/`);

    useEffect(() => {
        const handleShortcut = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setSearchOpen(true);
            }
            if (event.key === 'Escape') setSearchOpen(false);
            if (event.key === 'Escape') setAccountOpen(false);
        };
        window.addEventListener('keydown', handleShortcut);

        return () => window.removeEventListener('keydown', handleShortcut);
    }, []);

    useEffect(() => {
        document.documentElement.lang = app.locale.replace('_', '-');
        document.documentElement.dir = app.direction;
    }, [app.direction, app.locale]);

    useEffect(() => {
        if (searchOpen) window.setTimeout(() => searchInput.current?.focus(), 0);
    }, [searchOpen]);

    useEffect(() => {
        const handleOutsideClick = (event: MouseEvent) => {
            if (accountMenu.current && !accountMenu.current.contains(event.target as Node)) {
                setAccountOpen(false);
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);

        return () => document.removeEventListener('mousedown', handleOutsideClick);
    }, []);

    useEffect(() => {
        const value = search.trim();
        if (isPlatformOperator || !searchOpen || value.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setSearching(true);
            fetch(`/search?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : { results: [] }))
                .then((payload: { results?: SearchResult[] }) => setSearchResults(payload.results ?? []))
                .catch(() => {
                    if (!controller.signal.aborted) setSearchResults([]);
                })
                .finally(() => {
                    if (!controller.signal.aborted) setSearching(false);
                });
        }, 180);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [isPlatformOperator, search, searchOpen]);

    const workspaceGroups: NavigationGroup[] = [
        {
            label: 'Workspace',
            items: [
                { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
                { label: 'Customers', href: '/customers', icon: Users, permission: 'customers.view' },
                { label: 'Plans', href: '/plans', icon: Tags, permission: 'plans.manage' },
                { label: 'Services', href: '/services', icon: Wifi, permission: 'services.view' },
            ],
        },
        {
            label: 'Billing',
            items: [
                { label: 'Billing', href: '/billing/invoices', icon: ReceiptText, permission: 'billing.invoices.view' },
                {
                    label: 'Credit notes',
                    href: '/billing/credit-notes',
                    icon: FilePlus2,
                    permission: 'billing.invoices.view',
                },
                { label: 'Payments', href: '/billing/payments', icon: CreditCard, permission: 'payments.collect' },
                { label: 'Cash shifts', href: '/billing/shifts', icon: WalletCards, permission: 'payments.collect' },
                { label: 'FX rates', href: '/billing/exchange-rates', icon: Scale, permission: 'settings.manage' },
                { label: 'Expenses', href: '/operations/expenses', icon: Banknote, permission: 'expenses.view' },
            ],
        },
        {
            label: 'Field operations',
            items: [
                {
                    label: 'Collector check-ins',
                    href: '/operations/collector-check-ins',
                    icon: MapPinned,
                    permission: 'reports.operations',
                },
                {
                    label: 'Collector routes',
                    href: '/operations/collector-routes',
                    icon: Navigation,
                    permission: 'reports.operations',
                },
                {
                    label: 'Collector tasks',
                    href: '/operations/collector-tasks',
                    icon: ClipboardCheck,
                    permission: 'reports.operations',
                },
                {
                    label: 'Collector custody',
                    href: '/operations/collector-custody',
                    icon: HandCoins,
                    permission: 'reports.operations',
                },
            ],
        },
        {
            label: 'Support & work',
            items: [
                { label: 'Tickets', href: '/operations/tickets', icon: MessageSquare, permission: 'tickets.view' },
                {
                    label: 'Work orders',
                    href: '/operations/work-orders',
                    icon: ClipboardList,
                    permission: 'workorders.complete',
                },
                {
                    label: 'Work-order calendar',
                    href: '/operations/work-orders/calendar',
                    icon: CalendarDays,
                    permission: 'workorders.complete',
                },
            ],
        },
        {
            label: 'Network',
            items: [
                {
                    label: 'Buildings & boxes',
                    href: '/operations/topology/buildings',
                    icon: Building2,
                    permission: 'network.view',
                },
                { label: 'Optical access', href: '/operations/optical', icon: Network, permission: 'network.view' },
                { label: 'Live sessions', href: '/operations/sessions', icon: Radio, permission: 'network.view' },
                { label: 'Incidents', href: '/operations/incidents', icon: CircleAlert, permission: 'network.view' },
                {
                    label: 'Network queue',
                    href: '/operations/network-commands',
                    icon: Wrench,
                    permission: 'network.view',
                },
                { label: 'Routers', href: '/operations/routers', icon: Router, permission: 'network.view' },
                { label: 'POPs', href: '/operations/pops', icon: Network, permission: 'network.view' },
                { label: 'IP pools', href: '/operations/ip-pools', icon: Network, permission: 'network.view' },
            ],
        },
        {
            label: 'Inventory & partners',
            items: [
                { label: 'Inventory', href: '/operations/inventory', icon: Package, permission: 'inventory.view' },
                {
                    label: 'Imports',
                    href: '/operations/imports',
                    icon: FileUp,
                    permission: [
                        'customers.create',
                        'plans.manage',
                        'services.create',
                        'inventory.receive',
                        'billing.adjustments.create',
                        'network.view',
                    ],
                },
                { label: 'Credentials', href: '/operations/credentials', icon: KeyRound, permission: 'suppliers.view' },
                { label: 'Suppliers', href: '/operations/suppliers', icon: Store, permission: 'suppliers.view' },
                { label: 'Partners', href: '/partners/commercial', icon: Store, permission: 'wallets.view' },
            ],
        },
        {
            label: 'Insights',
            items: [
                { label: 'Reports', href: '/reports/operations', icon: BarChart3, permission: 'reports.operations' },
            ],
        },
        {
            label: 'Settings',
            items: [
                { label: 'Settings', href: '/settings', icon: Wrench, permission: 'settings.manage' },
                {
                    label: 'Notification templates',
                    href: '/settings/notification-templates',
                    icon: MessageSquare,
                    permission: 'settings.manage',
                },
            ],
        },
    ];
    const fieldNav: NavigationItem[] = [
        { label: 'Operations', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Collect', href: '/field', icon: HandCoins, permission: 'payments.collect' },
        { label: 'Customers', href: '/customers', icon: Users, permission: 'customers.view' },
        { label: 'Payments', href: '/billing/payments', icon: CreditCard, permission: 'payments.collect' },
        { label: 'Shifts', href: '/billing/shifts', icon: WalletCards, permission: 'payments.collect' },
        { label: 'Work', href: '/operations/work-orders', icon: ClipboardList, permission: 'workorders.complete' },
    ].filter((item) => item.permission === undefined || can(item.permission));
    const collectorMode = matchesPath('/field');
    const navGroups: NavigationGroup[] = isPlatformOperator
        ? [{ label: 'Platform', items: [{ label: 'Tenants', href: '/admin/tenants', icon: Building2 }] }]
        : collectorMode
          ? [{ label: 'Collector desk', items: fieldNav }]
          : workspaceGroups
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => item.permission === undefined || can(item.permission)),
                }))
                .filter((group) => group.items.length > 0);
    const nav = navGroups.flatMap((group) => group.items);
    const activeNavHref = nav
        .filter((item) => matchesPath(item.href))
        .sort((left, right) => right.href.length - left.href.length)[0]?.href;
    const activeGroupLabel = navGroups.find((group) => group.items.some((item) => item.href === activeNavHref))?.label;

    return (
        <div className="min-h-dvh bg-canvas text-ink" dir={app.direction}>
            <RealtimeBridge />
            <Toaster />
            <aside className="fixed inset-y-0 start-0 z-20 hidden w-64 flex-col border-e border-line bg-white lg:flex">
                <div className="flex h-20 shrink-0 items-center gap-3 border-b border-line px-6">
                    <div className="grid size-9 place-items-center overflow-hidden rounded-xl bg-brand text-white shadow-sm">
                        {auth.tenant?.logo_url ? (
                            <img src={auth.tenant.logo_url} alt="" className="size-full object-cover" />
                        ) : (
                            <Activity size={19} />
                        )}
                    </div>
                    <div>
                        <p className="font-display text-sm font-bold tracking-tight">ISP Manager</p>
                        <p className="text-xs text-muted">{t('Operations desk')}</p>
                    </div>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5">
                    <div className="mb-5 rounded-xl bg-sand px-3 py-3">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">
                            {t('Workspace')}
                        </p>
                        <p className="mt-1 truncate text-sm font-semibold">
                            {auth.tenant?.name ?? (isPlatformOperator ? 'Platform administration' : 'Platform')}
                        </p>
                    </div>
                    {canUseCollectorDesk && (
                        <Link
                            href={collectorMode ? '/dashboard' : '/field'}
                            className={`mb-5 flex items-center gap-3 rounded-xl border px-3 py-3 text-sm transition ${collectorMode ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-ink hover:border-brand/40 hover:bg-sand/60'}`}
                        >
                            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                                {collectorMode ? <LayoutDashboard size={18} /> : <HandCoins size={18} />}
                            </span>
                            <span className="min-w-0">
                                <span className="block font-semibold">
                                    {t(collectorMode ? 'Operations desk' : 'Collector desk')}
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-muted">
                                    {t(
                                        collectorMode
                                            ? 'Return to workspace management'
                                            : 'Routes, dues, cash and stock',
                                    )}
                                </span>
                            </span>
                        </Link>
                    )}
                    <nav className="space-y-2" aria-label={t('Workspace navigation')}>
                        {navGroups.map((group) => {
                            const isOpen =
                                openGroups[group.label] ??
                                (group.label === 'Workspace' ||
                                    group.label === 'Collector desk' ||
                                    group.label === activeGroupLabel);

                            return (
                                <div key={group.label}>
                                    {navGroups.length > 1 && (
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted hover:bg-sand hover:text-ink"
                                            aria-expanded={isOpen}
                                            onClick={() =>
                                                setOpenGroups((current) => ({ ...current, [group.label]: !isOpen }))
                                            }
                                        >
                                            <span>{t(group.label)}</span>
                                            <ChevronDown
                                                size={14}
                                                className={`transition-transform ${isOpen ? '' : '-rotate-90'}`}
                                            />
                                        </button>
                                    )}
                                    {isOpen && (
                                        <div className="mt-1 space-y-0.5">
                                            {group.items.map(({ label, href, icon: Icon }) => {
                                                const active = href === activeNavHref;

                                                return (
                                                    <Link
                                                        key={href}
                                                        href={href}
                                                        className={`group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${active ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-sand hover:text-ink'}`}
                                                        aria-current={active ? 'page' : undefined}
                                                    >
                                                        <Icon size={17} strokeWidth={active ? 2.2 : 1.8} />
                                                        <span>{t(label)}</span>
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </nav>
                </div>
            </aside>

            <div className="lg:ps-64">
                <header className="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-line bg-canvas/90 px-5 backdrop-blur lg:px-8">
                    <div className="flex items-center gap-3">
                        {!isPlatformOperator && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setSearchOpen(true)}
                                    className="hidden rounded-lg border border-line bg-white p-2 text-muted hover:text-brand lg:block"
                                    title={t('Search customers')}
                                    aria-label={t('Search customers')}
                                >
                                    <Command size={17} />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSearchOpen(true)}
                                    className="hidden items-center gap-2 text-sm text-muted hover:text-ink sm:flex"
                                >
                                    <Search size={16} />
                                    <span>{t('Search customers, services…')}</span>
                                    <kbd className="ms-2 rounded border border-line bg-white px-1.5 py-0.5 text-[10px]">
                                        ⌘ K
                                    </kbd>
                                </button>
                            </>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {!isPlatformOperator && (
                            <Link
                                href="/notifications"
                                className="relative rounded-lg p-2.5 text-muted hover:bg-white"
                                title={t('Open notifications center')}
                                aria-label={t('Open notifications center')}
                            >
                                <Bell size={19} strokeWidth={1.8} />
                            </Link>
                        )}
                        <div ref={accountMenu} className="relative border-s border-line ps-3">
                            <button
                                type="button"
                                onClick={() => setAccountOpen((open) => !open)}
                                className="flex items-center gap-3 rounded-xl p-1.5 text-start hover:bg-white"
                                aria-haspopup="menu"
                                aria-expanded={accountOpen}
                                aria-controls="account-menu"
                                aria-label={t('Open account menu')}
                            >
                                <span className="grid size-9 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand">
                                    {auth.user?.name.slice(0, 1).toUpperCase()}
                                </span>
                                <span className="hidden sm:block">
                                    <span className="block text-sm font-semibold">{auth.user?.name}</span>
                                    <span className="block text-xs capitalize text-muted">
                                        {roleLabel(auth.user?.role ?? '', t)}
                                    </span>
                                </span>
                                <ChevronDown size={16} className="text-muted" />
                            </button>
                            {accountOpen && (
                                <div
                                    id="account-menu"
                                    role="menu"
                                    aria-label={t('Account menu')}
                                    className="absolute end-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-white p-1 shadow-xl"
                                >
                                    {!isPlatformOperator && (
                                        <>
                                            <Link
                                                href="/profile"
                                                role="menuitem"
                                                className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-sand"
                                            >
                                                <UserRound size={16} className="text-brand" /> {t('Profile')}
                                            </Link>
                                            <Link
                                                href="/security/sessions"
                                                role="menuitem"
                                                className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-sand"
                                            >
                                                <KeyRound size={16} className="text-brand" /> {t('Active sessions')}
                                            </Link>
                                            {canUseCollectorDesk && (
                                                <Link
                                                    href="/field"
                                                    role="menuitem"
                                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-sand"
                                                >
                                                    <HandCoins size={16} className="text-brand" /> {t('Collector desk')}
                                                </Link>
                                            )}
                                            {can('settings.manage') && (
                                                <Link
                                                    href="/settings/general"
                                                    role="menuitem"
                                                    className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-sand"
                                                >
                                                    <Wrench size={16} className="text-brand" />{' '}
                                                    {t('Workspace settings')}
                                                </Link>
                                            )}
                                            <div className="mt-1 border-t border-line px-3 pb-2 pt-3">
                                                <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">
                                                    {t('Language')}
                                                </p>
                                                <div className="grid grid-cols-3 gap-1">
                                                    {(
                                                        [
                                                            ['en', 'English'],
                                                            ['ar', 'Arabic'],
                                                            ['fr', 'French'],
                                                        ] as const
                                                    ).map(([locale, label]) => (
                                                        <Form key={locale} action="/settings/locale" method="post">
                                                            <input type="hidden" name="locale" value={locale} />
                                                            <button
                                                                type="submit"
                                                                role="menuitem"
                                                                className={`w-full rounded-md px-2 py-2 text-xs font-semibold ${app.locale === locale ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-sand hover:text-ink'}`}
                                                                aria-current={app.locale === locale ? 'true' : undefined}
                                                            >
                                                                {t(label)}
                                                            </button>
                                                        </Form>
                                                    ))}
                                                </div>
                                            </div>
                                        </>
                                    )}
                                    <Form action="/logout" method="post" className="mt-1 border-t border-line pt-1">
                                        <button
                                            type="submit"
                                            role="menuitem"
                                            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-coral hover:bg-rose-50"
                                        >
                                            <LogOut size={16} /> {t('Sign out')}
                                        </button>
                                    </Form>
                                </div>
                            )}
                        </div>
                    </div>
                </header>
                <OfflineBanner />
                {searchOpen && (
                    <div
                        className="fixed inset-0 z-50 bg-ink/20 p-4 sm:p-8"
                        role="presentation"
                        onMouseDown={(event) => event.target === event.currentTarget && setSearchOpen(false)}
                    >
                        <div
                            className="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-line bg-white shadow-2xl"
                            role="dialog"
                            aria-modal="true"
                            aria-label={t('Global search')}
                        >
                            <div className="flex items-center gap-3 border-b border-line px-5 py-4">
                                <Search size={19} className="text-brand" />
                                <input
                                    ref={searchInput}
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onKeyDown={(event) => event.key === 'Escape' && setSearchOpen(false)}
                                    className="min-w-0 flex-1 border-0 bg-transparent text-base outline-none placeholder:text-muted"
                                    placeholder={t('Search pages, settings, customers, services…')}
                                    aria-label={t('Search workspace')}
                                />
                                <kbd className="rounded border border-line px-1.5 py-0.5 text-[10px] text-muted">
                                    ESC
                                </kbd>
                            </div>
                            <div className="max-h-[min(60vh,32rem)] overflow-y-auto p-2">
                                {searching && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">
                                        {t('Searching workspace…')}
                                    </p>
                                )}
                                {!searching && search.trim().length < 2 && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">
                                        {t('Type at least two characters to search.')}
                                    </p>
                                )}
                                {!searching && search.trim().length >= 2 && searchResults.length === 0 && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">
                                        {t('No matching records found.')}
                                    </p>
                                )}
                                {!searching &&
                                    search.trim().length >= 2 &&
                                    searchResults.map((result) => (
                                        <Link
                                            key={`${result.type}-${result.href}`}
                                            href={result.href}
                                            onClick={() => setSearchOpen(false)}
                                            className="flex items-center gap-3 rounded-xl px-3 py-3 hover:bg-sand"
                                        >
                                            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-xs font-bold uppercase text-brand">
                                                {(result.localized ? t('Page') : result.type).slice(0, 2)}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold">
                                                    {result.localized ? t(result.label) : result.label}
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs capitalize text-muted">
                                                    {result.localized ? t(result.detail) : result.detail} ·{' '}
                                                    {result.localized ? t('Page') : result.type}
                                                </span>
                                            </span>
                                        </Link>
                                    ))}
                            </div>
                        </div>
                    </div>
                )}
                <main className="mx-auto max-w-[1440px] px-5 py-8 pb-24 lg:px-8 lg:pb-8">{children}</main>
            </div>
            <nav
                className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] shadow-[0_-6px_20px_rgba(14,31,29,0.08)] backdrop-blur lg:hidden"
                aria-label={t('Field navigation')}
            >
                <div className="mx-auto flex max-w-lg items-stretch justify-around">
                    {fieldNav.map(({ label, href, icon: Icon }) => {
                        const active = matchesPath(href);

                        return (
                            <Link
                                key={href}
                                href={href}
                                className={`flex min-h-16 min-w-16 flex-1 flex-col items-center justify-center gap-1 px-1 text-[11px] font-semibold transition ${active ? 'text-brand' : 'text-muted hover:text-ink'}`}
                                aria-current={active ? 'page' : undefined}
                            >
                                <Icon size={20} strokeWidth={active ? 2.2 : 1.8} />
                                <span>{t(label)}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}

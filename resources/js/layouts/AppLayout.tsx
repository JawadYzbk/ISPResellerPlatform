import { Form, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Bell,
    CalendarDays,
    CircleAlert,
    ChevronDown,
    ClipboardList,
    CreditCard,
    FileUp,
    KeyRound,
    Command,
    LayoutDashboard,
    LogOut,
    MessageSquare,
    Network,
    Package,
    Radio,
    ReceiptText,
    Scale,
    Store,
    Search,
    Router,
    Tags,
    Users,
    Wifi,
    WalletCards,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type PropsWithChildren } from 'react';

import RealtimeBridge from '@/components/RealtimeBridge';
import OfflineBanner from '@/components/OfflineBanner';
import type { PageProps } from '@/types';

type NavigationItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    permission?: string | string[];
};

type SearchResult = { type: string; label: string; detail: string; href: string };

export default function AppLayout({ children }: PropsWithChildren) {
    const page = usePage<PageProps>();
    const { auth, app } = page.props;
    const { url } = page;
    const [searchOpen, setSearchOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [searching, setSearching] = useState(false);
    const searchInput = useRef<HTMLInputElement>(null);
    const can = (permission: string | string[]) =>
        Array.isArray(permission)
            ? permission.some((item) => auth.permissions.includes(item))
            : auth.permissions.includes(permission);

    useEffect(() => {
        const handleShortcut = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setSearchOpen(true);
            }
            if (event.key === 'Escape') setSearchOpen(false);
        };
        window.addEventListener('keydown', handleShortcut);

        return () => window.removeEventListener('keydown', handleShortcut);
    }, []);

    useEffect(() => {
        if (searchOpen) window.setTimeout(() => searchInput.current?.focus(), 0);
    }, [searchOpen]);

    useEffect(() => {
        const value = search.trim();
        if (!searchOpen || value.length < 2) {
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
    }, [search, searchOpen]);

    const nav: NavigationItem[] = [
        { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Customers', href: '/customers', icon: Users, permission: 'customers.view' },
        { label: 'Plans', href: '/plans', icon: Tags, permission: 'plans.manage' },
        { label: 'Services', href: '/services', icon: Wifi, permission: 'services.view' },
        { label: 'Billing', href: '/billing/invoices', icon: ReceiptText, permission: 'billing.invoices.view' },
        { label: 'Payments', href: '/billing/payments', icon: CreditCard, permission: 'payments.collect' },
        { label: 'Cash shifts', href: '/billing/shifts', icon: WalletCards, permission: 'payments.collect' },
        { label: 'FX rates', href: '/billing/exchange-rates', icon: Scale, permission: 'settings.manage' },
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
        { label: 'Partners', href: '/partners/commercial', icon: Store, permission: 'wallets.view' },
        { label: 'Reports', href: '/reports/operations', icon: BarChart3, permission: 'reports.operations' },
        { label: 'Live sessions', href: '/operations/sessions', icon: Radio, permission: 'network.view' },
        { label: 'Incidents', href: '/operations/incidents', icon: CircleAlert, permission: 'network.view' },
        { label: 'Network queue', href: '/operations/network-commands', icon: Wrench, permission: 'network.view' },
        { label: 'Routers', href: '/operations/routers', icon: Router, permission: 'network.view' },
        { label: 'POPs', href: '/operations/pops', icon: Network, permission: 'network.view' },
        { label: 'IP pools', href: '/operations/ip-pools', icon: Network, permission: 'network.view' },
        { label: 'Settings', href: '/settings/general', icon: Wrench, permission: 'settings.manage' },
    ].filter((item) => item.permission === undefined || can(item.permission));

    const fieldNav: NavigationItem[] = [
        { label: 'Home', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Customers', href: '/customers', icon: Users, permission: 'customers.view' },
        { label: 'Payments', href: '/billing/payments', icon: CreditCard, permission: 'payments.collect' },
        { label: 'Shifts', href: '/billing/shifts', icon: WalletCards, permission: 'payments.collect' },
        { label: 'Work', href: '/operations/work-orders', icon: ClipboardList, permission: 'workorders.complete' },
    ].filter((item) => item.permission === undefined || can(item.permission));

    return (
        <div className="min-h-screen bg-canvas text-ink" dir={app.direction}>
            <RealtimeBridge />
            <aside className="fixed inset-y-0 start-0 z-20 hidden w-64 flex-col border-e border-line bg-white lg:flex">
                <div className="flex h-20 items-center gap-3 border-b border-line px-6">
                    <div className="grid size-9 place-items-center rounded-xl bg-brand text-white shadow-sm">
                        <Activity size={19} />
                    </div>
                    <div>
                        <p className="font-display text-sm font-bold tracking-tight">ISP Manager</p>
                        <p className="text-xs text-muted">Operations desk</p>
                    </div>
                </div>
                <div className="px-4 py-5">
                    <div className="mb-5 rounded-xl bg-sand px-3 py-3">
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">Workspace</p>
                        <p className="mt-1 truncate text-sm font-semibold">{auth.tenant?.name ?? 'Platform'}</p>
                    </div>
                    <nav className="space-y-1">
                        {nav.map(({ label, href, icon: Icon }) => (
                            <Link
                                key={href}
                                href={href}
                                className="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition hover:bg-sand hover:text-ink"
                            >
                                <Icon size={18} strokeWidth={1.8} />
                                <span>{label}</span>
                            </Link>
                        ))}
                    </nav>
                </div>
                <div className="mt-auto border-t border-line p-4"></div>
            </aside>

            <div className="lg:ps-64">
                <header className="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-line bg-canvas/90 px-5 backdrop-blur lg:px-8">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            className="hidden rounded-lg border border-line bg-white p-2 text-muted hover:text-brand lg:block"
                            title="Search customers"
                            aria-label="Search customers"
                        >
                            <Command size={17} />
                        </button>
                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            className="hidden items-center gap-2 text-sm text-muted hover:text-ink sm:flex"
                        >
                            <Search size={16} />
                            <span>Search customers, services…</span>
                            <kbd className="ms-2 rounded border border-line bg-white px-1.5 py-0.5 text-[10px]">
                                ⌘ K
                            </kbd>
                        </button>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href="/dashboard#attention"
                            className="relative rounded-lg p-2.5 text-muted hover:bg-white"
                            title="Open manager attention queue"
                            aria-label="Open manager attention queue"
                        >
                            <Bell size={19} strokeWidth={1.8} />
                            <span className="absolute end-2 top-2 size-1.5 rounded-full bg-coral" />
                        </Link>
                        <div className="flex items-center gap-3 border-s border-line ps-3">
                            <div className="grid size-9 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand">
                                {auth.user?.name.slice(0, 1).toUpperCase()}
                            </div>
                            <div className="hidden text-start sm:block">
                                <p className="text-sm font-semibold">{auth.user?.name}</p>
                                <p className="text-xs capitalize text-muted">{auth.user?.role.replace('_', ' ')}</p>
                            </div>
                            <ChevronDown size={16} className="hidden text-muted sm:block" />
                        </div>
                        <Form action="/logout" method="post">
                            <button
                                type="submit"
                                className="rounded-lg p-2.5 text-muted hover:bg-white hover:text-coral"
                                title="Sign out"
                            >
                                <LogOut size={18} />
                            </button>
                        </Form>
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
                            aria-label="Global search"
                        >
                            <div className="flex items-center gap-3 border-b border-line px-5 py-4">
                                <Search size={19} className="text-brand" />
                                <input
                                    ref={searchInput}
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onKeyDown={(event) => event.key === 'Escape' && setSearchOpen(false)}
                                    className="min-w-0 flex-1 border-0 bg-transparent text-base outline-none placeholder:text-muted"
                                    placeholder="Search customer, service, IP, invoice, ticket…"
                                    aria-label="Search workspace"
                                />
                                <kbd className="rounded border border-line px-1.5 py-0.5 text-[10px] text-muted">
                                    ESC
                                </kbd>
                            </div>
                            <div className="max-h-[min(60vh,32rem)] overflow-y-auto p-2">
                                {searching && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">Searching workspace…</p>
                                )}
                                {!searching && search.trim().length < 2 && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">
                                        Type at least two characters to search.
                                    </p>
                                )}
                                {!searching && search.trim().length >= 2 && searchResults.length === 0 && (
                                    <p className="px-3 py-8 text-center text-sm text-muted">
                                        No matching records found.
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
                                                {result.type.slice(0, 2)}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold">
                                                    {result.label}
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs capitalize text-muted">
                                                    {result.detail} · {result.type}
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
                aria-label="Field navigation"
            >
                <div className="mx-auto flex max-w-lg items-stretch justify-around">
                    {fieldNav.map(({ label, href, icon: Icon }) => {
                        const active = url === href || url?.startsWith(`${href}/`) === true;

                        return (
                            <Link
                                key={href}
                                href={href}
                                className={`flex min-h-16 min-w-16 flex-1 flex-col items-center justify-center gap-1 px-1 text-[11px] font-semibold transition ${active ? 'text-brand' : 'text-muted hover:text-ink'}`}
                                aria-current={active ? 'page' : undefined}
                            >
                                <Icon size={20} strokeWidth={active ? 2.2 : 1.8} />
                                <span>{label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}

import { Form, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Bell,
    ChevronDown,
    CircleDollarSign,
    Command,
    LayoutDashboard,
    LogOut,
    Network,
    Search,
    Settings2,
    Users,
    Wifi,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';

import type { PageProps } from '@/types';

export default function AppLayout({ children }: PropsWithChildren) {
    const { auth, app } = usePage<PageProps>().props;

    const nav = [
        { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Customers', href: '/customers', icon: Users },
        { label: 'Services', href: '/services', icon: Wifi },
        { label: 'Billing', href: '/billing', icon: CircleDollarSign },
        { label: 'Network', href: '/network', icon: Network },
    ];

    return (
        <div className="min-h-screen bg-canvas text-ink" dir={app.direction}>
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
                <div className="mt-auto border-t border-line p-4">
                    <Link
                        href="/settings"
                        className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted hover:bg-sand hover:text-ink"
                    >
                        <Settings2 size={18} strokeWidth={1.8} />
                        Settings
                    </Link>
                </div>
            </aside>

            <div className="lg:ps-64">
                <header className="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-line bg-canvas/90 px-5 backdrop-blur lg:px-8">
                    <div className="flex items-center gap-3">
                        <button className="hidden rounded-lg border border-line bg-white p-2 text-muted lg:block">
                            <Command size={17} />
                        </button>
                        <div className="hidden items-center gap-2 text-sm text-muted sm:flex">
                            <Search size={16} />
                            <span>Search customers, services…</span>
                            <kbd className="ms-2 rounded border border-line bg-white px-1.5 py-0.5 text-[10px]">
                                ⌘ K
                            </kbd>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <button className="relative rounded-lg p-2.5 text-muted hover:bg-white">
                            <Bell size={19} strokeWidth={1.8} />
                            <span className="absolute end-2 top-2 size-1.5 rounded-full bg-coral" />
                        </button>
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
                <main className="mx-auto max-w-[1440px] px-5 py-8 lg:px-8">{children}</main>
            </div>
        </div>
    );
}

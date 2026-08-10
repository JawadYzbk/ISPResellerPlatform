import { Head, Link } from '@inertiajs/react';
import { LogOut, RefreshCw, Wifi } from 'lucide-react';
import { useEffect, useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import { formatDate } from '@/lib/format';
import type { Customer, PublicTenant } from '@/types';

type Props = { tenant: PublicTenant };

export default function PortalDashboard({ tenant }: Props) {
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [error, setError] = useState<string | null>(null);
    const tokenKey = `portal_token:${tenant.slug}`;

    useEffect(() => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            window.location.assign(`/portal/${tenant.slug}`);
            return;
        }
        fetch(`/api/v1/portal/${tenant.slug}/me`, { headers: { Authorization: `Bearer ${token}` } })
            .then(async (response) => {
                if (!response.ok) {
                    window.location.assign(`/portal/${tenant.slug}`);
                    return;
                }
                setCustomer(await response.json());
            })
            .catch(() => setError('The portal could not be loaded.'));
    }, [tenant.slug, tokenKey]);

    const signOut = () => {
        sessionStorage.removeItem(tokenKey);
        window.location.assign(`/portal/${tenant.slug}`);
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-8 text-ink">
            <Head title="Customer portal" />
            <main className="mx-auto max-w-3xl">
                <header className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-brand text-white">
                            <Wifi size={19} />
                        </div>
                        <div>
                            <p className="font-display font-bold">{tenant.name}</p>
                            <p className="text-sm text-muted">Customer portal</p>
                        </div>
                    </div>
                    <button onClick={signOut} className="button-secondary">
                        <LogOut size={16} />
                        Sign out
                    </button>
                </header>
                {error && <p className="mt-8 field-error">{error}</p>}
                {customer && (
                    <>
                        <div className="mt-12">
                            <p className="eyebrow">Welcome back</p>
                            <h1 className="page-title">
                                {customer.first_name} {customer.last_name ?? ''}
                            </h1>
                            <p className="page-subtitle">Your connections and service status at a glance.</p>
                        </div>
                        <section className="mt-8 space-y-4">
                            {customer.services.map((service) => (
                                <article key={service.public_id} className="card p-6">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-start gap-3">
                                            <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                                <Wifi size={18} />
                                            </div>
                                            <div>
                                                <h2 className="font-semibold">{service.plan.name}</h2>
                                                <p className="mt-1 text-sm text-muted">{service.username}</p>
                                            </div>
                                        </div>
                                        <StatusBadge status={service.status} />
                                    </div>
                                    <div className="mt-5 flex items-center justify-between border-t border-line pt-4 text-sm">
                                        <span className="text-muted">Expires {formatDate(service.expires_at)}</span>
                                        <span className="inline-flex items-center gap-1.5 text-muted">
                                            <RefreshCw size={14} />
                                            {service.network_state.replace('_', ' ')}
                                        </span>
                                    </div>
                                </article>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="card p-10 text-center">
                                    <p className="font-semibold">No services are linked to this account.</p>
                                    <p className="mt-1 text-sm text-muted">
                                        Contact your provider if this looks incorrect.
                                    </p>
                                </div>
                            )}
                        </section>
                    </>
                )}
            </main>
            <Link href={`/portal/${tenant.slug}`} className="sr-only">
                Return to portal sign in
            </Link>
        </div>
    );
}

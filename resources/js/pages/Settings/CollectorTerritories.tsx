import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { MapPinned, Save, Search, ShieldCheck, UserRoundCheck } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Zone = {
    id: number;
    parent_id: number | null;
    parent_name: string | null;
    name: string;
    code: string;
};

type Collector = {
    id: number;
    name: string;
    email: string;
    all_zones: boolean;
    zone_ids: number[];
};

type Props = {
    collectors: Collector[];
    zones: Zone[];
};

function CollectorTerritoryCard({ collector, zones }: { collector: Collector; zones: Zone[] }) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        all_zones: collector.all_zones,
        zone_ids: collector.zone_ids,
    });

    const toggleZone = (zoneId: number, checked: boolean) => {
        form.setData(
            'zone_ids',
            checked ? [...new Set([...form.data.zone_ids, zoneId])] : form.data.zone_ids.filter((id) => id !== zoneId),
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(`/settings/collector-territories/${collector.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="card p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <UserRoundCheck className="shrink-0 text-brand" size={18} />
                        <h2 className="section-title truncate">{collector.name}</h2>
                    </div>
                    <p className="mt-1 truncate text-sm text-muted">{collector.email}</p>
                </div>
                <button className="button-primary shrink-0" disabled={form.processing}>
                    <Save size={16} /> {form.processing ? t('Saving...') : t('territories.save')}
                </button>
            </div>

            <label className="mt-5 flex items-start gap-3 rounded-xl border border-line bg-sand/50 p-4">
                <input
                    type="checkbox"
                    checked={form.data.all_zones}
                    onChange={(event) => form.setData('all_zones', event.target.checked)}
                    className="mt-0.5"
                />
                <span>
                    <span className="block text-sm font-semibold">{t('territories.all_zones')}</span>
                    <span className="mt-1 block text-pretty text-xs text-muted">
                        {t('territories.all_zones_description')}
                    </span>
                </span>
            </label>

            {!form.data.all_zones && (
                <fieldset className="mt-5">
                    <legend className="field-label">{t('territories.assigned_zones')}</legend>
                    <div className="mt-2 grid max-h-72 gap-2 overflow-y-auto rounded-xl border border-line p-3 sm:grid-cols-2">
                        {zones.map((zone) => (
                            <label
                                key={zone.id}
                                className="flex items-start gap-3 rounded-lg px-3 py-2.5 hover:bg-sand"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={form.data.zone_ids.includes(zone.id)}
                                    onChange={(event) => toggleZone(zone.id, event.target.checked)}
                                />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium">{zone.name}</span>
                                    <span className="block truncate text-xs text-muted">
                                        {zone.code}
                                        {zone.parent_name ? ` · ${t('territories.under')} ${zone.parent_name}` : ''}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {zones.length === 0 && (
                        <div className="mt-2 text-sm text-amber-700">
                            <p>{t('territories.create_zone_first')}</p>
                            <Link href="/settings/locations" className="mt-2 inline-flex font-semibold text-brand">
                                {t('territories.create_zone')} →
                            </Link>
                        </div>
                    )}
                </fieldset>
            )}

            {form.errors.all_zones && <p className="field-error mt-3">{t(form.errors.all_zones)}</p>}
            {form.errors.zone_ids && <p className="field-error mt-3">{t(form.errors.zone_ids)}</p>}
        </form>
    );
}

export default function CollectorTerritories({ collectors, zones }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState('');
    const term = search.trim().toLocaleLowerCase();
    const visibleCollectors = collectors.filter(
        (collector) =>
            term === '' ||
            collector.name.toLocaleLowerCase().includes(term) ||
            collector.email.toLocaleLowerCase().includes(term),
    );

    return (
        <AppLayout>
            <Head title={t('Collector territories')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('territories.setup')}</p>
                    <h1 className="page-title text-balance">{t('Collector territories')}</h1>
                    <p className="page-subtitle text-pretty">{t('territories.subtitle')}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link href="/settings/users" className="button-secondary">
                        {t('Users and invitations')}
                    </Link>
                    <Link href="/settings/locations" className="button-secondary">
                        {t('locations.title')}
                    </Link>
                </div>
            </div>

            <section className="card mt-6 flex items-start gap-3 p-5">
                <ShieldCheck className="mt-0.5 shrink-0 text-brand" size={19} />
                <div>
                    <h2 className="text-sm font-semibold">{t('territories.server_enforced')}</h2>
                    <p className="mt-1 text-pretty text-sm text-muted">{t('territories.server_description')}</p>
                </div>
            </section>

            {collectors.length > 0 && (
                <div className="relative mt-6 max-w-xl">
                    <Search className="pointer-events-none absolute start-3 top-3 text-muted" size={17} />
                    <input
                        className="field ps-10"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('territories.search')}
                        aria-label={t('territories.search')}
                    />
                </div>
            )}

            <div className="mt-6 grid gap-5 xl:grid-cols-2">
                {visibleCollectors.map((collector) => (
                    <CollectorTerritoryCard key={collector.id} collector={collector} zones={zones} />
                ))}
            </div>

            {collectors.length === 0 && (
                <section className="card mt-6 p-10 text-center">
                    <MapPinned className="mx-auto text-muted" size={30} />
                    <h2 className="mt-3 text-balance font-semibold">{t('territories.no_collectors')}</h2>
                    <p className="mx-auto mt-2 max-w-lg text-pretty text-sm text-muted">
                        {t('territories.no_collectors_description')}
                    </p>
                    <Link href="/settings/users" className="button-primary mt-5">
                        {t('territories.invite_collector')}
                    </Link>
                </section>
            )}

            {collectors.length > 0 && visibleCollectors.length === 0 && (
                <section className="card mt-6 p-8 text-center">
                    <p className="font-semibold">{t('territories.no_matches')}</p>
                    <button type="button" className="button-secondary mt-4" onClick={() => setSearch('')}>
                        {t('Clear search')}
                    </button>
                </section>
            )}
        </AppLayout>
    );
}

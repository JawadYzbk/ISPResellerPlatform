import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, WalletCards } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type Shift = {
    public_id: string;
    status: 'open' | 'closed';
    opened_at: string | null;
    closed_at: string | null;
    system_totals: Record<string, number>;
    declared_totals: Record<string, number>;
    variance: boolean;
    variance_note: string | null;
    collector: string | null;
};

type DailyReport = {
    date: string;
    payment_count: number;
    totals: Record<string, number>;
    variance_shift_count: number;
    collectors: { name: string; payment_count: number; totals: Record<string, number> }[];
};

type Props = PageProps & {
    shifts: Paginator<Shift>;
    currentShift: Shift | null;
    currencies: string[];
    canViewReport: boolean;
    dailyReport: DailyReport | null;
};

function Pager({ shifts, t }: { shifts: Paginator<Shift>; t: (key: string) => string }) {
    return (
        <div className="flex items-center justify-between border-t border-line px-5 py-4">
            <p className="text-xs text-muted">
                {t('Page')} {shifts.current_page} {t('of')} {shifts.last_page}
            </p>
            <div className="flex items-center gap-1">
                {shifts.links.map((link, index) => {
                    const previous = index === 0;
                    const next = index === shifts.links.length - 1;
                    if (!link.url) {
                        return (
                            <span key={index} className="grid size-8 place-items-center text-muted/40">
                                {previous ? <ChevronLeft size={16} /> : next ? <ChevronRight size={16} /> : t(link.label)}
                            </span>
                        );
                    }
                    return (
                        <Link
                            key={index}
                            href={link.url}
                            className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                        >
                                {previous ? <ChevronLeft size={16} /> : next ? <ChevronRight size={16} /> : t(link.label)}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export default function ShiftsPage({ shifts, currentShift, currencies, canViewReport, dailyReport }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error mt-2" role="alert">
                {t(error)}
            </p>
        ) : null;
    const [declaredTotals, setDeclaredTotals] = useState<Record<string, string>>(
        Object.fromEntries(currencies.map((currency) => [currency, ''])),
    );
    const form = useForm({ declared_totals: {} as Record<string, number>, variance_note: '' });

    const submitClose = (event: React.FormEvent) => {
        event.preventDefault();
        const totals: Record<string, number> = {};
        for (const currency of currencies) {
            const value = declaredTotals[currency]?.trim() ?? '';
            if (value === '') {
                totals[currency] = 0;
                continue;
            }
            const minor = parseMoneyToMinor(value, currency);
            if (minor === null) {
                form.setError('declared_totals', `${t('shifts.valid_amount')} ${currency}.`);
                return;
            }
            totals[currency] = minor;
        }
        form.clearErrors('declared_totals');
        form.transform(() => ({ declared_totals: totals, variance_note: form.data.variance_note }));
        form.post(`/billing/shifts/${currentShift?.public_id}/close`);
    };

    const applyReportDate = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const date = new FormData(event.currentTarget).get('date');
        router.get('/billing/shifts', { date }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={t('Cash shifts')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('shifts.billing_controls')}</p>
                    <h1 className="page-title">{t('Cash shifts')}</h1>
                    <p className="page-subtitle">{t('shifts.subtitle')}</p>
                </div>
                {!currentShift && (
                    <button
                        type="button"
                        className="button-primary"
                        onClick={() => router.post('/billing/shifts/open')}
                    >
                        <WalletCards size={16} /> {t('shifts.open_cash_shift')}
                    </button>
                )}
            </div>

            {currentShift ? (
                <div className="card mt-8 border-brand/30 p-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p className="eyebrow">{t('shifts.current_shift')}</p>
                            <h2 className="section-title mt-1">
                                {t('shifts.opened')} {formatDate(currentShift.opened_at)}
                            </h2>
                            <p className="mt-1 text-sm text-muted">{t('shifts.system_total_note')}</p>
                        </div>
                        <StatusBadge status="open" />
                    </div>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        {currencies.map((currency) => (
                            <div key={currency} className="rounded-xl bg-sand p-4">
                                <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                    {t('System total')} · {currency}
                                </p>
                                <p className="mt-2 font-display text-2xl font-semibold">
                                    {formatMoney(currentShift.system_totals[currency] ?? 0, currency)}
                                </p>
                            </div>
                        ))}
                    </div>
                    <form onSubmit={submitClose} className="mt-6 border-t border-line pt-6">
                        <fieldset
                            className="grid gap-5 sm:grid-cols-2"
                            aria-invalid={Boolean(form.errors.declared_totals)}
                            aria-describedby={
                                form.errors.declared_totals ? 'cash-shift-declared-totals-error' : undefined
                            }
                        >
                            <legend className="sr-only">{t('shifts.counted_cash')}</legend>
                            {currencies.map((currency) => (
                                <label key={currency}>
                                    <span className="field-label">
                                        {t('shifts.counted_cash')} ({currency})
                                    </span>
                                    <input
                                        id={`cash-shift-counted-${currency.toLowerCase()}`}
                                        className="field"
                                        type="number"
                                        min="0"
                                        step={currency === 'JPY' ? '1' : '0.01'}
                                        aria-invalid={Boolean(form.errors.declared_totals)}
                                        aria-describedby={
                                            form.errors.declared_totals
                                                ? 'cash-shift-declared-totals-error'
                                                : undefined
                                        }
                                        value={declaredTotals[currency] ?? ''}
                                        onChange={(event) =>
                                            setDeclaredTotals((values) => ({
                                                ...values,
                                                [currency]: event.target.value,
                                            }))
                                        }
                                        placeholder="0.00"
                                    />
                                </label>
                            ))}
                        </fieldset>
                        {fieldError('cash-shift-declared-totals', form.errors.declared_totals)}
                        <label className="mt-5 block">
                            <span className="field-label">{t('shifts.variance_note')}</span>
                            <textarea
                                id="cash-shift-variance-note"
                                className="field min-h-24"
                                {...fieldA11y('cash-shift-variance-note', form.errors.variance_note)}
                                value={form.data.variance_note}
                                onChange={(event) => form.setData('variance_note', event.target.value)}
                                placeholder={t('shifts.variance_placeholder')}
                            />
                            {fieldError('cash-shift-variance-note', form.errors.variance_note)}
                        </label>
                        <div className="mt-5 flex justify-end">
                            <button type="submit" className="button-primary" disabled={form.processing}>
                                <CheckCircle2 size={16} /> {t('shifts.close_reconcile')}
                            </button>
                        </div>
                    </form>
                </div>
            ) : (
                <div className="card mt-8 flex items-center gap-3 p-6 text-sm text-muted">
                    <WalletCards size={18} className="text-brand" /> {t('shifts.no_open_shift')}
                </div>
            )}

            {canViewReport && dailyReport && (
                <section className="card mt-6 overflow-hidden">
                    <div className="flex flex-col justify-between gap-4 border-b border-line px-5 py-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="eyebrow">{t('shifts.manager_report')}</p>
                            <h2 className="section-title mt-1">{t('Collector totals')} · {dailyReport.date}</h2>
                            <p className="mt-1 text-xs text-muted">
                                {dailyReport.payment_count} {t('posted payment(s)')} · {dailyReport.variance_shift_count}{' '}
                                {t('variance shift(s)')}
                            </p>
                        </div>
                        <form onSubmit={applyReportDate}>
                            <label className="field-label">
                                {t('shifts.report_date')}
                                <input className="field mt-1" type="date" name="date" defaultValue={dailyReport.date} />
                            </label>
                        </form>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-start">
                            <thead>
                                <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                    <th className="px-5 py-3.5 text-start">{t('Collector')}</th>
                                    <th className="px-5 py-3.5 text-start">{t('Payments')}</th>
                                    <th className="px-5 py-3.5 text-start">{t('Totals')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {dailyReport.collectors.map((collector) => (
                                    <tr key={collector.name}>
                                        <td className="px-5 py-4 text-sm font-semibold">{collector.name}</td>
                                        <td className="px-5 py-4 text-sm text-muted">{collector.payment_count}</td>
                                        <td className="px-5 py-4 text-sm text-muted">
                                            {entriesOrEmpty(collector.totals).map(([currency, amount]) => (
                                                <p key={currency}>{formatMoney(amount, currency)}</p>
                                            ))}
                                        </td>
                                    </tr>
                                ))}
                                {dailyReport.collectors.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-5 py-10 text-center text-sm text-muted">
                                            {t('shifts.no_posted_cash')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                    <WalletCards size={17} className="text-brand" />
                    <p className="text-sm font-semibold">{t('shifts.history')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                {canViewReport && <th className="px-5 py-3.5 text-start">{t('Collector')}</th>}
                                <th className="px-5 py-3.5 text-start">{t('Opened')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('shifts.system_total')}</th>
                                <th className="px-5 py-3.5 text-start">{t('shifts.declared_total')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Variance')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {shifts.data.map((shift) => (
                                <tr key={shift.public_id}>
                                    {canViewReport && (
                                        <td className="px-5 py-4 text-sm font-semibold">
                                            {shift.collector ?? t('System')}
                                        </td>
                                    )}
                                    <td className="px-5 py-4 text-sm">
                                        {formatDate(shift.opened_at)}
                                        <p className="mt-1 text-xs text-muted">{formatDate(shift.closed_at)}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={shift.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {entriesOrEmpty(shift.system_totals).map(([currency, amount]) => (
                                            <p key={currency}>{formatMoney(amount, currency)}</p>
                                        ))}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {entriesOrEmpty(shift.declared_totals).map(([currency, amount]) => (
                                            <p key={currency}>{formatMoney(amount, currency)}</p>
                                        ))}
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        {shift.variance ? (
                                            <span className="font-semibold text-coral">
                                                {t('Flagged')} · {shift.variance_note}
                                            </span>
                                        ) : (
                                            <span className="text-muted">{t('Balanced')}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {shifts.data.length === 0 && (
                                <tr>
                                    <td colSpan={canViewReport ? 6 : 5} className="px-5 py-14 text-center">
                                        <p className="font-semibold">{t('shifts.no_history')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pager shifts={shifts} t={t} />
            </div>
        </AppLayout>
    );
}

import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ChevronLeft, ChevronRight, WalletCards } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
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

function Pager({ shifts }: { shifts: Paginator<Shift> }) {
    return (
        <div className="flex items-center justify-between border-t border-line px-5 py-4">
            <p className="text-xs text-muted">Page {shifts.current_page} of {shifts.last_page}</p>
            <div className="flex items-center gap-1">
                {shifts.links.map((link, index) => {
                    const previous = index === 0;
                    const next = index === shifts.links.length - 1;
                    if (!link.url) {
                        return <span key={index} className="grid size-8 place-items-center text-muted/40">{previous ? <ChevronLeft size={16} /> : next ? <ChevronRight size={16} /> : link.label}</span>;
                    }
                    return <Link key={index} href={link.url} className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}>{previous ? <ChevronLeft size={16} /> : next ? <ChevronRight size={16} /> : link.label}</Link>;
                })}
            </div>
        </div>
    );
}

export default function ShiftsPage({ shifts, currentShift, currencies, canViewReport, dailyReport }: Props) {
    const [declaredTotals, setDeclaredTotals] = useState<Record<string, string>>(Object.fromEntries(currencies.map((currency) => [currency, ''])));
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
                form.setError('declared_totals', `Enter a valid ${currency} amount.`);
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
            <Head title="Cash shifts" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><p className="eyebrow">Billing controls</p><h1 className="page-title">Cash shifts</h1><p className="page-subtitle">Open a till, compare posted collections, and close with an auditable variance note.</p></div>
                {!currentShift && <button type="button" className="button-primary" onClick={() => router.post('/billing/shifts/open')}><WalletCards size={16} /> Open cash shift</button>}
            </div>

            {currentShift ? <div className="card mt-8 border-brand/30 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p className="eyebrow">Current shift</p><h2 className="section-title mt-1">Opened {formatDate(currentShift.opened_at)}</h2><p className="mt-1 text-sm text-muted">Only posted payments assigned to this shift count toward the system total.</p></div><StatusBadge status="open" /></div>
                <div className="mt-6 grid gap-4 sm:grid-cols-2">{currencies.map((currency) => <div key={currency} className="rounded-xl bg-sand p-4"><p className="text-xs font-semibold uppercase tracking-wider text-muted">System total · {currency}</p><p className="mt-2 font-display text-2xl font-semibold">{formatMoney(currentShift.system_totals[currency] ?? 0, currency)}</p></div>)}</div>
                <form onSubmit={submitClose} className="mt-6 border-t border-line pt-6"><div className="grid gap-5 sm:grid-cols-2">{currencies.map((currency) => <label key={currency}><span className="field-label">Counted cash ({currency})</span><input className="field" type="number" min="0" step={currency === 'JPY' ? '1' : '0.01'} value={declaredTotals[currency] ?? ''} onChange={(event) => setDeclaredTotals((values) => ({ ...values, [currency]: event.target.value }))} placeholder="0.00" /></label>)}</div><label className="mt-5 block"><span className="field-label">Variance note (required when totals differ)</span><textarea className="field min-h-24" value={form.data.variance_note} onChange={(event) => form.setData('variance_note', event.target.value)} placeholder="Explain any shortage or overage." /></label>{form.errors.declared_totals && <p className="field-error mt-2">{form.errors.declared_totals}</p>}<div className="mt-5 flex justify-end"><button className="button-primary" disabled={form.processing}><CheckCircle2 size={16} /> Close and reconcile</button></div></form>
            </div> : <div className="card mt-8 flex items-center gap-3 p-6 text-sm text-muted"><WalletCards size={18} className="text-brand" /> No open cash shift. Open one before recording cash collections.</div>}

            {canViewReport && dailyReport && <section className="card mt-6 overflow-hidden"><div className="flex flex-col justify-between gap-4 border-b border-line px-5 py-4 sm:flex-row sm:items-end"><div><p className="eyebrow">Manager report</p><h2 className="section-title mt-1">Collector totals · {dailyReport.date}</h2><p className="mt-1 text-xs text-muted">{dailyReport.payment_count} posted payment(s) · {dailyReport.variance_shift_count} variance shift(s)</p></div><form onSubmit={applyReportDate}><label className="field-label">Report date<input className="field mt-1" type="date" name="date" defaultValue={dailyReport.date} /></label></form></div><div className="overflow-x-auto"><table className="w-full min-w-[640px] text-start"><thead><tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted"><th className="px-5 py-3.5 text-start">Collector</th><th className="px-5 py-3.5 text-start">Payments</th><th className="px-5 py-3.5 text-start">Totals</th></tr></thead><tbody className="divide-y divide-line">{dailyReport.collectors.map((collector) => <tr key={collector.name}><td className="px-5 py-4 text-sm font-semibold">{collector.name}</td><td className="px-5 py-4 text-sm text-muted">{collector.payment_count}</td><td className="px-5 py-4 text-sm text-muted">{Object.entries(collector.totals).map(([currency, amount]) => <p key={currency}>{formatMoney(amount, currency)}</p>)}</td></tr>)}{dailyReport.collectors.length === 0 && <tr><td colSpan={3} className="px-5 py-10 text-center text-sm text-muted">No posted cash payments for this date.</td></tr>}</tbody></table></div></section>}

            <div className="card mt-6 overflow-hidden"><div className="flex items-center gap-2 border-b border-line px-5 py-4"><WalletCards size={17} className="text-brand" /><p className="text-sm font-semibold">Shift history</p></div><div className="overflow-x-auto"><table className="w-full min-w-[820px] text-start"><thead><tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">{canViewReport && <th className="px-5 py-3.5 text-start">Collector</th>}<th className="px-5 py-3.5 text-start">Opened</th><th className="px-5 py-3.5 text-start">Status</th><th className="px-5 py-3.5 text-start">System total</th><th className="px-5 py-3.5 text-start">Declared total</th><th className="px-5 py-3.5 text-start">Variance</th></tr></thead><tbody className="divide-y divide-line">{shifts.data.map((shift) => <tr key={shift.public_id}>{canViewReport && <td className="px-5 py-4 text-sm font-semibold">{shift.collector ?? 'System'}</td>}<td className="px-5 py-4 text-sm">{formatDate(shift.opened_at)}<p className="mt-1 text-xs text-muted">{formatDate(shift.closed_at)}</p></td><td className="px-5 py-4"><StatusBadge status={shift.status} /></td><td className="px-5 py-4 text-sm text-muted">{Object.entries(shift.system_totals).map(([currency, amount]) => <p key={currency}>{formatMoney(amount, currency)}</p>)}</td><td className="px-5 py-4 text-sm text-muted">{Object.entries(shift.declared_totals).map(([currency, amount]) => <p key={currency}>{formatMoney(amount, currency)}</p>)}</td><td className="px-5 py-4 text-sm">{shift.variance ? <span className="font-semibold text-coral">Flagged · {shift.variance_note}</span> : <span className="text-muted">Balanced</span>}</td></tr>)}{shifts.data.length === 0 && <tr><td colSpan={canViewReport ? 6 : 5} className="px-5 py-14 text-center"><p className="font-semibold">No shift history yet</p></td></tr>}</tbody></table></div><Pager shifts={shifts} /></div>
        </AppLayout>
    );
}

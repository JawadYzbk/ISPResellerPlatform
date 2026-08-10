import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Tags } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, parseMoneyToMinor } from '@/lib/format';

export default function PlanCreate() {
    const form = useForm({ name: '', slug: '', download_kbps: '', upload_kbps: '', duration_days: '30', amount: '', currency: 'USD', effective_from: new Date().toISOString().slice(0, 10), status: 'active' });
    const fractionDigits = currencyFractionDigits(form.data.currency);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(form.data.amount, form.data.currency);
        if (amountMinor === null) {
            form.setError('amount', 'Enter a valid non-negative amount.');
            return;
        }
        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount_minor: amountMinor }));
        form.post('/plans');
    };

    return (
        <AppLayout>
            <Head title="New plan" />
            <Link href="/plans" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"><ArrowLeft size={16} /> Back to plans</Link>
            <div className="max-w-3xl"><p className="eyebrow">Commercial catalog</p><h1 className="page-title">New plan</h1><p className="page-subtitle">Create the plan definition and its first effective price in one transaction.</p></div>
            <form onSubmit={submit} className="card mt-8 max-w-3xl space-y-6 p-6">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div><label className="field-label" htmlFor="name">Plan name</label><input id="name" className="field" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Home 100" />{form.errors.name && <p className="field-error">{form.errors.name}</p>}</div>
                    <div><label className="field-label" htmlFor="slug">Slug (optional)</label><input id="slug" className="field" value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} placeholder="home-100" />{form.errors.slug && <p className="field-error">{form.errors.slug}</p>}</div>
                    <div><label className="field-label" htmlFor="download_kbps">Download (kbps)</label><input id="download_kbps" type="number" min="0" className="field" value={form.data.download_kbps} onChange={(event) => form.setData('download_kbps', event.target.value)} placeholder="100000" />{form.errors.download_kbps && <p className="field-error">{form.errors.download_kbps}</p>}</div>
                    <div><label className="field-label" htmlFor="upload_kbps">Upload (kbps)</label><input id="upload_kbps" type="number" min="0" className="field" value={form.data.upload_kbps} onChange={(event) => form.setData('upload_kbps', event.target.value)} placeholder="20000" />{form.errors.upload_kbps && <p className="field-error">{form.errors.upload_kbps}</p>}</div>
                    <div><label className="field-label" htmlFor="duration_days">Duration (days)</label><input id="duration_days" type="number" min="1" className="field" value={form.data.duration_days} onChange={(event) => form.setData('duration_days', event.target.value)} />{form.errors.duration_days && <p className="field-error">{form.errors.duration_days}</p>}</div>
                    <div><label className="field-label" htmlFor="amount">Price ({form.data.currency})</label><input id="amount" type="number" min="0" step={fractionDigits === 0 ? '1' : '0.01'} className="field" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} placeholder="35.00" />{form.errors.amount && <p className="field-error">{form.errors.amount}</p>}</div>
                    <div><label className="field-label" htmlFor="currency">Currency</label><input id="currency" maxLength={3} className="field uppercase" value={form.data.currency} onChange={(event) => form.setData('currency', event.target.value.toUpperCase())} />{form.errors.currency && <p className="field-error">{form.errors.currency}</p>}</div>
                    <div><label className="field-label" htmlFor="effective_from">Price effective from</label><input id="effective_from" type="date" className="field" value={form.data.effective_from} onChange={(event) => form.setData('effective_from', event.target.value)} />{form.errors.effective_from && <p className="field-error">{form.errors.effective_from}</p>}</div>
                </div>
                <div><label className="field-label" htmlFor="status">Plan status</label><select id="status" className="field" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div className="flex items-center gap-2 rounded-xl border border-line bg-sand/40 p-4 text-sm text-muted"><Tags size={17} className="text-brand" /> Prices are stored as integer minor units with an effective date.</div>
                <div className="flex justify-end gap-3 border-t border-line pt-5"><Link href="/plans" className="button-secondary">Cancel</Link><button className="button-primary" disabled={form.processing}><Save size={16} /> Create plan</button></div>
            </form>
        </AppLayout>
    );
}

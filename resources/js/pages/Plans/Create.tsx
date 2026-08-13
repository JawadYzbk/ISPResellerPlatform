import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save, Tags } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, parseMoneyToMinor } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function PlanCreate({ currencies }: { currencies: CurrencyOption[] }) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        name: '',
        slug: '',
        download_kbps: '',
        upload_kbps: '',
        duration_days: '30',
        amount: '',
        currency: 'USD',
        effective_from: new Date().toISOString().slice(0, 10),
        status: 'active',
    });
    const fractionDigits = currencyFractionDigits(form.data.currency);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(form.data.amount, form.data.currency);
        if (amountMinor === null) {
            form.setError('amount', t('plan.valid_non_negative_amount'));
            return;
        }
        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount_minor: amountMinor }));
        form.post('/plans');
    };

    return (
        <AppLayout>
            <Head title={t('plan.new_plan')} />
            <Link
                href="/plans"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('plan.back_to_plans')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('plan.commercial_catalog')}</p>
                <h1 className="page-title">{t('plan.new_plan')}</h1>
                <p className="page-subtitle">{t('plan.create_description')}</p>
            </div>
            <form onSubmit={submit} className="card mt-8 max-w-3xl space-y-6 p-6">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label className="field-label" htmlFor="name">
                            {t('plan.plan_name')}
                        </label>
                        <input
                            id="name"
                            className="field"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value)}
                            placeholder={t('plan.home_100')}
                        />
                        {form.errors.name && <p className="field-error">{t(form.errors.name)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="slug">
                            {t('plan.slug_optional')}
                        </label>
                        <input
                            id="slug"
                            className="field"
                            value={form.data.slug}
                            onChange={(event) => form.setData('slug', event.target.value)}
                            placeholder="home-100"
                        />
                        {form.errors.slug && <p className="field-error">{t(form.errors.slug)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="download_kbps">
                            {t('plan.download_kbps')}
                        </label>
                        <input
                            id="download_kbps"
                            type="number"
                            min="0"
                            className="field"
                            value={form.data.download_kbps}
                            onChange={(event) => form.setData('download_kbps', event.target.value)}
                            placeholder="100000"
                        />
                        {form.errors.download_kbps && <p className="field-error">{t(form.errors.download_kbps)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="upload_kbps">
                            {t('plan.upload_kbps')}
                        </label>
                        <input
                            id="upload_kbps"
                            type="number"
                            min="0"
                            className="field"
                            value={form.data.upload_kbps}
                            onChange={(event) => form.setData('upload_kbps', event.target.value)}
                            placeholder="20000"
                        />
                        {form.errors.upload_kbps && <p className="field-error">{t(form.errors.upload_kbps)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="duration_days">
                            {t('plan.duration_days')}
                        </label>
                        <input
                            id="duration_days"
                            type="number"
                            min="1"
                            className="field"
                            value={form.data.duration_days}
                            onChange={(event) => form.setData('duration_days', event.target.value)}
                        />
                        {form.errors.duration_days && <p className="field-error">{t(form.errors.duration_days)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="amount">
                            {t('Price')} ({form.data.currency})
                        </label>
                        <input
                            id="amount"
                            type="number"
                            min="0"
                            step={fractionDigits === 0 ? '1' : '0.01'}
                            className="field"
                            value={form.data.amount}
                            onChange={(event) => form.setData('amount', event.target.value)}
                            placeholder="35.00"
                        />
                        {form.errors.amount && <p className="field-error">{t(form.errors.amount)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="currency">
                            {t('Currency')}
                        </label>
                        <CurrencyCombobox
                            id="currency"
                            className="field"
                            value={form.data.currency}
                            currencies={currencies}
                            onChange={(value) => form.setData('currency', value)}
                        />
                        {form.errors.currency && <p className="field-error">{t(form.errors.currency)}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="effective_from">
                            {t('plan.price_effective_from')}
                        </label>
                        <input
                            id="effective_from"
                            type="date"
                            className="field"
                            value={form.data.effective_from}
                            onChange={(event) => form.setData('effective_from', event.target.value)}
                        />
                        {form.errors.effective_from && <p className="field-error">{t(form.errors.effective_from)}</p>}
                    </div>
                </div>
                <div>
                    <label className="field-label" htmlFor="status">
                        {t('plan.plan_status')}
                    </label>
                    <ResponsiveSelect
                        id="status"
                        className="field"
                        value={form.data.status}
                        onChange={(event) => form.setData('status', event.target.value)}
                    >
                        <option value="active">{t('Active')}</option>
                        <option value="inactive">{t('Inactive')}</option>
                    </ResponsiveSelect>
                </div>
                <div className="flex items-center gap-2 rounded-xl border border-line bg-sand/40 p-4 text-sm text-muted">
                    <Tags size={17} className="text-brand" /> {t('plan.router_rate_limit_preview')}:{' '}
                    <code className="font-semibold text-ink">
                        {form.data.upload_kbps || '0'}k/{form.data.download_kbps || '0'}k
                    </code>
                </div>
                <div className="flex items-center gap-2 rounded-xl border border-line bg-sand/40 p-4 text-sm text-muted">
                    <Tags size={17} className="text-brand" /> {t('plan.price_storage_note')}
                </div>
                <div className="flex justify-end gap-3 border-t border-line pt-5">
                    <Link href="/plans" className="button-secondary">
                        {t('Cancel')}
                    </Link>
                    <button className="button-primary" disabled={form.processing}>
                        <Save size={16} /> {t('plan.create_plan')}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}

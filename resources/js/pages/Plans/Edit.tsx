import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save, Tags } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, parseMoneyToMinor } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Plan = {
    public_id: string;
    name: string;
    slug: string;
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    amount_minor: number;
    currency: string;
    effective_from: string;
    status: 'active' | 'inactive';
};

type Props = { plan: Plan; currencies: CurrencyOption[] };

function minorToInput(amountMinor: number, currency: string): string {
    const digits = currencyFractionDigits(currency);

    return (amountMinor / 10 ** digits).toFixed(digits).replace(/\.0+$|(?<=\.[0-9]*)0+$/, '');
}

export default function PlanEdit({ plan, currencies }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        name: plan.name,
        slug: plan.slug,
        download_kbps: String(plan.download_kbps),
        upload_kbps: String(plan.upload_kbps),
        duration_days: String(plan.duration_days),
        amount: minorToInput(plan.amount_minor, plan.currency),
        currency: plan.currency,
        effective_from: plan.effective_from.slice(0, 10),
        status: plan.status,
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
        form.put(`/plans/${plan.public_id}`);
    };

    return (
        <AppLayout>
            <Head title={`${t('Edit')} ${plan.name}`} />
            <Link
                href="/plans"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('plan.back_to_plans')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('plan.commercial_catalog')}</p>
                <h1 className="page-title">{t('plan.edit_plan')}</h1>
                <p className="page-subtitle">{t('plan.edit_description')}</p>
            </div>
            <form onSubmit={submit} className="card mt-8 max-w-3xl space-y-6 p-6">
                <div className="grid gap-5 sm:grid-cols-2">
                    {(
                        [
                            ['name', t('plan.plan_name'), t('plan.home_100')],
                            ['slug', t('plan.slug'), 'home-100'],
                            ['download_kbps', t('plan.download_kbps'), '100000'],
                            ['upload_kbps', t('plan.upload_kbps'), '20000'],
                            ['duration_days', t('plan.duration_days'), '30'],
                        ] as const
                    ).map(([key, label, placeholder]) => (
                        <label key={key}>
                            <span className="field-label">{label}</span>
                            <input
                                className="field"
                                type={key === 'name' || key === 'slug' ? 'text' : 'number'}
                                min={key === 'duration_days' ? 1 : 0}
                                value={form.data[key]}
                                onChange={(event) => form.setData(key, event.target.value)}
                                placeholder={placeholder}
                            />
                            {form.errors[key] && <p className="field-error">{form.errors[key]}</p>}
                        </label>
                    ))}
                    <label>
                        <span className="field-label">
                            {t('Price')} ({form.data.currency})
                        </span>
                        <input
                            className="field"
                            type="number"
                            min="0"
                            step={fractionDigits === 0 ? '1' : '0.01'}
                            value={form.data.amount}
                            onChange={(event) => form.setData('amount', event.target.value)}
                            placeholder="35.00"
                        />
                        {form.errors.amount && <p className="field-error">{form.errors.amount}</p>}
                    </label>
                    <label>
                        <span className="field-label">{t('Currency')}</span>
                        <CurrencyCombobox
                            className="field"
                            value={form.data.currency}
                            currencies={currencies}
                            onChange={(value) => form.setData('currency', value)}
                        />
                        {form.errors.currency && <p className="field-error">{form.errors.currency}</p>}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.price_effective_from')}</span>
                        <input
                            className="field"
                            type="date"
                            value={form.data.effective_from}
                            onChange={(event) => form.setData('effective_from', event.target.value)}
                        />
                        {form.errors.effective_from && <p className="field-error">{form.errors.effective_from}</p>}
                    </label>
                </div>
                <label>
                    <span className="field-label">{t('plan.plan_status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={form.data.status}
                        onChange={(event) => form.setData('status', event.target.value as 'active' | 'inactive')}
                    >
                        <option value="active">{t('Active')}</option>
                        <option value="inactive">{t('Inactive')}</option>
                    </ResponsiveSelect>
                </label>
                <div className="flex items-center gap-2 rounded-xl border border-line bg-sand/40 p-4 text-sm text-muted">
                    <Tags size={17} className="text-brand" /> {t('plan.invoice_snapshot_note')}
                </div>
                <div className="flex justify-end gap-3 border-t border-line pt-5">
                    <Link href="/plans" className="button-secondary">
                        {t('Cancel')}
                    </Link>
                    <button className="button-primary" disabled={form.processing}>
                        <Save size={16} /> {t('plan.save_plan')}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}

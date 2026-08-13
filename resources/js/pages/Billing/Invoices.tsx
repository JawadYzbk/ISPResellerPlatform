import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type InvoiceRow = {
    public_id: string;
    number: string;
    status: 'draft' | 'issued' | 'void';
    currency: string;
    subtotal_amount: number;
    tax_amount: number;
    total_amount: number;
    allocated_amount: number;
    outstanding_amount: number;
    due_at: string | null;
    issued_at: string | null;
    customer: { public_id: string; code: string; name: string };
};

type Props = PageProps & {
    invoices: Paginator<InvoiceRow>;
    filters: { status?: string; search?: string };
    canIssue?: boolean;
};

export default function InvoicesPage({ invoices, filters, canIssue = false }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/billing/invoices',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('Invoices')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Billing operations')}</p>
                    <h1 className="page-title">{t('Invoices')}</h1>
                    <p className="page-subtitle">
                        {t('Review issued balances and move draft invoices into the ledger.')}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {canIssue && (
                        <>
                            <Link href="/billing/bulk-renewals" className="button-secondary">
                                {t('Bulk renewals')}
                            </Link>
                            <Link href="/billing/invoices/create" className="button-primary">
                                <Plus size={16} /> {t('Create invoice')}
                            </Link>
                        </>
                    )}
                    <Link href="/reports/finance" className="button-secondary">
                        {t('Finance report')}
                    </Link>
                </div>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">{t('Search invoice or customer')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('INV-0001, customer code, name')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('Invoice status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('All statuses')}</option>
                        <option value="draft">{t('Draft')}</option>
                        <option value="issued">{t('Issued')}</option>
                        <option value="void">{t('Void')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <FileText size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {invoices.total.toLocaleString()} {t('invoice(s)')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('Balances use posted payment allocations.')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1080px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Invoice')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Total')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Outstanding')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Due')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {invoices.data.map((invoice) => (
                                <tr key={invoice.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/billing/invoices/${invoice.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {invoice.number}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('Issued')} {formatDate(invoice.issued_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/customers/${invoice.customer.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {invoice.customer.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{invoice.customer.code}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={invoice.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold">
                                        {formatMoney(invoice.total_amount, invoice.currency)}
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <span
                                            className={
                                                invoice.outstanding_amount > 0
                                                    ? 'font-semibold text-coral'
                                                    : 'text-muted'
                                            }
                                        >
                                            {formatMoney(invoice.outstanding_amount, invoice.currency)}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(invoice.due_at)}</td>
                                    <td className="px-5 py-4 text-end">
                                        {canIssue && invoice.status === 'draft' && (
                                            <button
                                                type="button"
                                                className="text-sm font-semibold text-brand"
                                                onClick={() =>
                                                    router.post(`/billing/invoices/${invoice.public_id}/issue`)
                                                }
                                            >
                                                {t('Issue invoice')}
                                            </button>
                                        )}
                                        {invoice.outstanding_amount > 0 && invoice.status === 'issued' && (
                                            <Link
                                                href={`/customers/${invoice.customer.public_id}/payments/create`}
                                                className="text-sm font-semibold text-brand"
                                            >
                                                {t('Take payment')}
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-16 text-center">
                                        <FileText className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('No invoices match these filters')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {invoices.current_page} {t('of')} {invoices.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {invoices.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === invoices.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? (
                                            <ChevronLeft size={16} />
                                        ) : isNext ? (
                                            <ChevronRight size={16} />
                                        ) : (
                                            link.label
                                        )}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? (
                                        <ChevronLeft size={16} />
                                    ) : isNext ? (
                                        <ChevronRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

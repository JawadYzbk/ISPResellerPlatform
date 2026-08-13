import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, FilePlus2, Search } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type CreditNoteRow = {
    public_id: string;
    number: string;
    amount: number;
    currency: string;
    status: string;
    reason: string;
    issued_at: string | null;
    invoice: { public_id: string; number: string };
    customer: { public_id: string; code: string; name: string };
    creator: string | null;
};

type Props = PageProps & {
    creditNotes: Paginator<CreditNoteRow>;
    filters: { search?: string };
};

export default function CreditNotesPage({ creditNotes, filters }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search ?? '');

    const applySearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/billing/credit-notes', { search: search || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={t('Credit notes')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Credit-note operations')}</p>
                    <h1 className="page-title">{t('Credit notes')}</h1>
                    <p className="page-subtitle">{t('Review issued credits and the invoices they adjust.')}</p>
                </div>
                <Link href="/billing/invoices" className="button-secondary">
                    {t('Back to invoices')}
                </Link>
            </div>

            <form onSubmit={applySearch} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">{t('Search credit note or customer')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('CN-0001, invoice, customer')}
                        />
                    </div>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <FilePlus2 size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {creditNotes.total.toLocaleString()} {t('credit note(s)')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('Credits are recorded as append-only ledger adjustments.')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1080px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Credit note')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Invoice')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Amount')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Reason')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Issued')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Created by')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {creditNotes.data.map((note) => (
                                <tr key={note.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{note.number}</p>
                                        <p className="mt-1 text-xs capitalize text-muted">{enumLabel(note.status, t)}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/billing/invoices/${note.invoice.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {note.invoice.number}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/customers/${note.customer.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {note.customer.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{note.customer.code}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold text-emerald-700">
                                        − {formatMoney(note.amount, note.currency)}
                                    </td>
                                    <td className="max-w-sm px-5 py-4 text-sm text-muted">{note.reason}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(note.issued_at)}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{note.creator ?? t('System')}</td>
                                </tr>
                            ))}
                            {creditNotes.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-16 text-center">
                                        <FilePlus2 className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('No credit notes match this search')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {creditNotes.current_page} {t('of')} {creditNotes.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {creditNotes.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === creditNotes.links.length - 1;
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

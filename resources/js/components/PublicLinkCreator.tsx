import { router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Link2 } from 'lucide-react';
import { useId, useState } from 'react';

import ResponsiveSelect from '@/components/ui/responsive-select';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type LinkType = 'invoice' | 'payment' | 'statement' | 'receipt';
export type PublicLinkSummary = {
    public_id: string;
    type: LinkType;
    expires_at: string;
    revoked_at: string | null;
    access_count: number;
    is_active: boolean;
};

export default function PublicLinkCreator({
    endpoint,
    types,
    title,
    existingLinks = [],
}: {
    endpoint: string;
    types: { value: LinkType; label: string }[];
    title?: string;
    existingLinks?: PublicLinkSummary[];
}) {
    const page = usePage<PageProps>();
    const { flash } = page.props;
    const t = createTranslator(page.props.app.locale);
    const fieldId = useId();
    const [copied, setCopied] = useState(false);
    const form = useForm({ type: types[0]?.value ?? 'statement', expires_in_days: 7 });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(endpoint, { preserveScroll: true, onSuccess: () => setCopied(false) });
    };
    const copy = async () => {
        if (!flash.publicLink?.url) return;
        await navigator.clipboard.writeText(flash.publicLink.url);
        setCopied(true);
    };

    return (
        <section className="card p-5 print:hidden">
            <div className="flex items-start gap-3">
                <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                    <Link2 size={17} />
                </span>
                <div>
                    <h2 className="text-sm font-semibold text-balance">{title ?? t('Share securely')}</h2>
                    <p className="mt-1 text-pretty text-xs text-muted">
                        {t('Create a revocable link without exposing customer login credentials.')}
                    </p>
                </div>
            </div>
            <form className="mt-4 grid gap-3 sm:grid-cols-[1fr_8rem_auto]" onSubmit={submit}>
                {types.length > 1 ? (
                    <ResponsiveSelect
                        aria-label={t('Public link type')}
                        value={form.data.type}
                        onChange={(event) => form.setData('type', event.target.value as LinkType)}
                    >
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </ResponsiveSelect>
                ) : (
                    <input type="hidden" value={form.data.type} readOnly />
                )}
                <label className="sr-only" htmlFor={`${fieldId}-expiry`}>
                    {t('Link expiry')}
                </label>
                <ResponsiveSelect
                    id={`${fieldId}-expiry`}
                    aria-label={t('Link expiry')}
                    value={form.data.expires_in_days}
                    onChange={(event) => form.setData('expires_in_days', Number(event.target.value))}
                >
                    <option value="1">{t('1 day')}</option>
                    <option value="7">{t('7 days')}</option>
                    <option value="30">{t('30 days')}</option>
                    <option value="90">{t('90 days')}</option>
                </ResponsiveSelect>
                <button className="button-secondary justify-center" disabled={form.processing}>
                    {t('Create link')}
                </button>
            </form>
            {form.errors.type && <p className="field-error mt-2">{t(form.errors.type)}</p>}
            {flash.publicLink && (
                <div className="mt-4 rounded-xl border border-line bg-sand/30 p-3">
                    <label className="field-label" htmlFor={`${fieldId}-url`}>
                        {t('One-time link')}
                    </label>
                    <div className="mt-1 flex gap-2">
                        <input
                            id={`${fieldId}-url`}
                            className="field min-w-0 flex-1 text-xs"
                            readOnly
                            value={flash.publicLink.url}
                        />
                        <button type="button" className="button-primary shrink-0" onClick={copy}>
                            {copied ? <Check size={15} /> : <Copy size={15} />}
                            <span className="hidden sm:inline">{copied ? t('Copied') : t('Copy')}</span>
                        </button>
                    </div>
                    <p className="mt-2 text-xs text-muted">
                        {t('Expires')} {formatDate(flash.publicLink.expires_at)}. {t('The token is not stored in readable form.')}
                    </p>
                </div>
            )}
            {existingLinks.length > 0 && (
                <div className="mt-4 divide-y divide-line border-t border-line">
                    {existingLinks.map((link) => {
                        return (
                            <div key={link.public_id} className="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p className="text-xs font-semibold capitalize">
                                {enumLabel(link.type, t)} {t('link')} ·{' '}
                                        {link.is_active ? t('Active') : link.revoked_at ? t('Revoked') : t('Expired')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted tabular-nums">
                                        {link.access_count} {t('view(s)')} · {t('expires')} {formatDate(link.expires_at)}
                                    </p>
                                </div>
                                {link.is_active && (
                                    <ConfirmDialog
                                        title={t('Revoke this public link?')}
                                        description={t('Anyone using the existing URL will immediately lose access. A new link can be created later.')}
                                        confirmLabel={t('Revoke link')}
                                        destructive
                                        onConfirm={() =>
                                            router.delete(`/billing/public-links/${link.public_id}`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <button type="button" className="button-danger">
                                            {t('Revoke')}
                                        </button>
                                    </ConfirmDialog>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

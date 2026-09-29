import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronDown,
    ChevronUp,
    CreditCard,
    Download,
    LogOut,
    RefreshCw,
    Send,
    UserRound,
    Wifi,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import { createIdempotencyKey } from '@/lib/idempotency';
import type {
    Customer,
    PortalBalance,
    PortalBilling,
    PortalCursorPage,
    PortalInvoice,
    PortalInvoiceDetail,
    PortalNotice,
    PortalPayment,
    PortalTicket,
    PublicTenant,
} from '@/types';

type Props = { tenant: PublicTenant };
type StripeIntent = { clientSecret: string; publishableKey: string; invoiceId: string; requestKey: string };
type PortalLoadKey = 'customer' | 'balance' | 'billing' | 'notices' | 'tickets' | 'invoices' | 'payments';
type PortalLoadState = Record<PortalLoadKey, { loading: boolean; error: boolean }>;

class PortalAuthenticationError extends Error {
    constructor() {
        super('Portal session expired');
    }
}

class PortalRequestError extends Error {
    constructor(
        public detail: string | null = null,
        public status: number | null = null,
    ) {
        super('Portal request failed');
    }
}

function isJsonObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isCursorPage<T>(value: unknown): value is PortalCursorPage<T> {
    if (!isJsonObject(value) || !Array.isArray(value.data) || !isJsonObject(value.meta)) {
        return false;
    }

    return (
        (value.meta.next_cursor === null || typeof value.meta.next_cursor === 'string') &&
        (value.meta.prev_cursor === null || typeof value.meta.prev_cursor === 'string') &&
        typeof value.meta.per_page === 'number'
    );
}

async function fetchPortalResponse(url: string, token: string, init: RequestInit = {}): Promise<Response> {
    const headers = new Headers(init.headers);
    headers.set('Authorization', 'Bearer ' + token);
    const response = await fetch(url, { ...init, headers });

    if (response.status === 401) {
        throw new PortalAuthenticationError();
    }

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);
        const detail = isJsonObject(payload)
            ? typeof payload.detail === 'string'
                ? payload.detail
                : typeof payload.message === 'string'
                  ? payload.message
                  : null
            : null;

        throw new PortalRequestError(detail, response.status);
    }

    return response;
}

async function fetchPortalJson<T>(url: string, token: string, init: RequestInit = {}): Promise<T> {
    const response = await fetchPortalResponse(url, token, init);

    if (!response.headers.get('content-type')?.toLowerCase().includes('application/json')) {
        throw new PortalRequestError();
    }

    const payload: unknown = await response.json().catch(() => null);
    if (!isJsonObject(payload)) {
        throw new PortalRequestError();
    }

    return payload as T;
}

function portalErrorMessage(error: unknown, t: (key: string) => string, fallback: string): string {
    return error instanceof PortalRequestError && error.detail ? t(error.detail) : t(fallback);
}

function isDefinitivePortalFailure(error: unknown): boolean {
    return error instanceof PortalRequestError && error.status !== null && error.status < 500 && error.status !== 408;
}

export default function PortalDashboard({ tenant }: Props) {
    const t = useMemo(() => createTranslator(tenant.locale), [tenant.locale]);

    useEffect(() => {
        document.documentElement.lang = tenant.locale;
        document.documentElement.dir = tenant.locale === 'ar' ? 'rtl' : 'ltr';
    }, [tenant.locale]);
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [balance, setBalance] = useState<PortalBalance | null>(null);
    const [billing, setBilling] = useState<PortalBilling | null>(null);
    const [invoices, setInvoices] = useState<PortalInvoice[]>([]);
    const [payments, setPayments] = useState<PortalPayment[]>([]);
    const [invoiceCursor, setInvoiceCursor] = useState<string | null>(null);
    const [paymentCursor, setPaymentCursor] = useState<string | null>(null);
    const [invoiceDetails, setInvoiceDetails] = useState<Record<string, PortalInvoiceDetail>>({});
    const [openInvoiceId, setOpenInvoiceId] = useState<string | null>(null);
    const [invoiceDetailBusy, setInvoiceDetailBusy] = useState<string | null>(null);
    const [invoiceDetailError, setInvoiceDetailError] = useState<string | null>(null);
    const [downloadBusyId, setDownloadBusyId] = useState<string | null>(null);
    const [notices, setNotices] = useState<PortalNotice[]>([]);
    const [tickets, setTickets] = useState<PortalTicket[]>([]);
    const [loadState, setLoadState] = useState<PortalLoadState>({
        customer: { loading: true, error: false },
        balance: { loading: true, error: false },
        billing: { loading: true, error: false },
        notices: { loading: true, error: false },
        tickets: { loading: true, error: false },
        invoices: { loading: true, error: false },
        payments: { loading: true, error: false },
    });
    const [ticketForm, setTicketForm] = useState({ category: 'other', subject: '', description: '' });
    const [profileForm, setProfileForm] = useState({ email: '', address: '' });
    const [ticketBusy, setTicketBusy] = useState(false);
    const [ratingBusy, setRatingBusy] = useState<string | null>(null);
    const [supportMessage, setSupportMessage] = useState<string | null>(null);
    const [profileBusy, setProfileBusy] = useState(false);
    const [profileSaved, setProfileSaved] = useState(false);
    const [restartBusy, setRestartBusy] = useState<string | null>(null);
    const [restartRequestedId, setRestartRequestedId] = useState<string | null>(null);
    const [selectedInvoiceId, setSelectedInvoiceId] = useState('');
    const [paymentIntent, setPaymentIntent] = useState<StripeIntent | null>(null);
    const [paymentBusy, setPaymentBusy] = useState(false);
    const [paymentMessage, setPaymentMessage] = useState<string | null>(null);
    const [paymentMessageError, setPaymentMessageError] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const paymentKeys = useRef(new Map<string, string>());
    const restartKeys = useRef(new Map<string, string>());
    const invoicePageCursors = useRef<string[]>([]);
    const paymentPageCursors = useRef<string[]>([]);
    const tokenKey = 'portal_token:' + tenant.slug;

    const redirectToSignIn = useCallback(() => {
        sessionStorage.removeItem(tokenKey);
        window.location.assign('/portal/' + tenant.slug);
    }, [tenant.slug, tokenKey]);

    const handleUnauthorized = useCallback(
        (requestError: unknown): boolean => {
            if (!(requestError instanceof PortalAuthenticationError)) {
                return false;
            }

            redirectToSignIn();
            return true;
        },
        [redirectToSignIn],
    );

    const loadSection = useCallback(
        async <T,>(key: PortalLoadKey, path: string, update: (payload: T) => void): Promise<T | null> => {
            const token = sessionStorage.getItem(tokenKey);
            if (!token) {
                redirectToSignIn();
                return null;
            }

            setLoadState((current) => ({ ...current, [key]: { loading: true, error: false } }));
            try {
                const payload = await fetchPortalJson<T>(path, token);
                update(payload);
                setLoadState((current) => ({ ...current, [key]: { loading: false, error: false } }));
                return payload;
            } catch (requestError) {
                if (!handleUnauthorized(requestError)) {
                    setLoadState((current) => ({ ...current, [key]: { loading: false, error: true } }));
                }
                return null;
            }
        },
        [handleUnauthorized, redirectToSignIn, tokenKey],
    );

    const loadCustomer = useCallback(
        () =>
            loadSection<Customer>('customer', '/api/v1/portal/' + tenant.slug + '/me', (payload) => {
                setCustomer(payload);
                setProfileForm({ email: payload.email ?? '', address: payload.address ?? '' });
            }),
        [loadSection, tenant.slug],
    );
    const loadBalance = useCallback(
        () => loadSection<PortalBalance>('balance', '/api/v1/portal/' + tenant.slug + '/me/balance', setBalance),
        [loadSection, tenant.slug],
    );
    const loadBilling = useCallback(
        () => loadSection<PortalBilling>('billing', '/api/v1/portal/' + tenant.slug + '/billing', setBilling),
        [loadSection, tenant.slug],
    );
    const loadNotices = useCallback(
        () =>
            loadSection<{ data?: PortalNotice[] }>(
                'notices',
                '/api/v1/portal/' + tenant.slug + '/me/notices',
                (payload) => setNotices(payload.data ?? []),
            ),
        [loadSection, tenant.slug],
    );
    const loadTickets = useCallback(
        () =>
            loadSection<{ data?: PortalTicket[] }>(
                'tickets',
                '/api/v1/portal/' + tenant.slug + '/me/tickets',
                (payload) => setTickets(payload.data ?? []),
            ),
        [loadSection, tenant.slug],
    );
    const loadInvoicePage = useCallback(
        (cursor?: string, append = false) => {
            const query = new URLSearchParams({ per_page: '25' });
            if (cursor) query.set('cursor', cursor);

            return loadSection<PortalCursorPage<PortalInvoice>>(
                'invoices',
                '/api/v1/portal/' + tenant.slug + '/me/invoices?' + query.toString(),
                (payload) => {
                    if (!isCursorPage<PortalInvoice>(payload)) {
                        throw new PortalRequestError();
                    }
                    if (append && cursor && !invoicePageCursors.current.includes(cursor)) {
                        invoicePageCursors.current.push(cursor);
                    } else if (!append) {
                        invoicePageCursors.current = [];
                    }
                    setInvoices((current) => (append ? [...current, ...payload.data] : payload.data));
                    setInvoiceCursor(payload.meta.next_cursor);
                },
            );
        },
        [loadSection, tenant.slug],
    );
    const loadPaymentPage = useCallback(
        (cursor?: string, append = false) => {
            const query = new URLSearchParams({ per_page: '25' });
            if (cursor) query.set('cursor', cursor);

            return loadSection<PortalCursorPage<PortalPayment>>(
                'payments',
                '/api/v1/portal/' + tenant.slug + '/me/payments?' + query.toString(),
                (payload) => {
                    if (!isCursorPage<PortalPayment>(payload)) {
                        throw new PortalRequestError();
                    }
                    if (append && cursor && !paymentPageCursors.current.includes(cursor)) {
                        paymentPageCursors.current.push(cursor);
                    } else if (!append) {
                        paymentPageCursors.current = [];
                    }
                    setPayments((current) => (append ? [...current, ...payload.data] : payload.data));
                    setPaymentCursor(payload.meta.next_cursor);
                },
            );
        },
        [loadSection, tenant.slug],
    );

    useEffect(() => {
        let active = true;
        const loadInitialData = async () => {
            await Promise.resolve();
            if (!active) return;
            if (!sessionStorage.getItem(tokenKey)) {
                redirectToSignIn();
                return;
            }

            await Promise.all([
                loadCustomer(),
                loadBalance(),
                loadBilling(),
                loadNotices(),
                loadTickets(),
                loadInvoicePage(),
                loadPaymentPage(),
            ]);
        };

        void loadInitialData();
        return () => {
            active = false;
        };
    }, [
        loadBalance,
        loadBilling,
        loadCustomer,
        loadInvoicePage,
        loadNotices,
        loadPaymentPage,
        loadTickets,
        redirectToSignIn,
        tokenKey,
    ]);

    const payableInvoices = invoices.filter((invoice) => invoice.status === 'issued' && invoice.outstanding_amount > 0);
    const selectedPayableInvoice =
        payableInvoices.find((invoice) => invoice.id === selectedInvoiceId) ?? payableInvoices[0];

    const signOut = async () => {
        const token = sessionStorage.getItem(tokenKey);
        if (token) {
            await fetch(`/api/v1/portal/${tenant.slug}/logout`, {
                method: 'POST',
                headers: { Authorization: `Bearer ${token}` },
            }).catch(() => undefined);
        }
        sessionStorage.removeItem(tokenKey);
        window.location.assign(`/portal/${tenant.slug}`);
    };

    const saveProfile = async (event: React.FormEvent) => {
        event.preventDefault();
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }
        setProfileBusy(true);
        setProfileSaved(false);
        setError(null);
        try {
            const payload = await fetchPortalJson<{ data: Partial<Customer> }>(
                '/api/v1/portal/' + tenant.slug + '/me/profile',
                token,
                {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(profileForm),
                },
            );
            setCustomer((current) => (current ? { ...current, ...payload.data } : current));
            setProfileSaved(true);
        } catch (requestError) {
            if (!handleUnauthorized(requestError)) {
                setError(portalErrorMessage(requestError, t, 'portal.dashboard.profile_error'));
            }
        } finally {
            setProfileBusy(false);
        }
    };

    const restartService = async (serviceId: string) => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }
        setRestartBusy(serviceId);
        setRestartRequestedId(null);
        setError(null);
        let idempotencyKey = restartKeys.current.get(serviceId);
        if (!idempotencyKey) {
            idempotencyKey = createIdempotencyKey('portal-restart');
            restartKeys.current.set(serviceId, idempotencyKey);
        }
        try {
            await fetchPortalResponse(
                '/api/v1/portal/' + tenant.slug + '/me/services/' + encodeURIComponent(serviceId) + '/restart-session',
                token,
                {
                    method: 'POST',
                    headers: { 'X-Idempotency-Key': idempotencyKey },
                },
            );
            restartKeys.current.delete(serviceId);
            setRestartRequestedId(serviceId);
        } catch (requestError) {
            if (isDefinitivePortalFailure(requestError)) {
                restartKeys.current.delete(serviceId);
            }
            if (!handleUnauthorized(requestError)) {
                setError(portalErrorMessage(requestError, t, 'portal.dashboard.restart_error'));
            }
        } finally {
            setRestartBusy(null);
        }
    };

    const startOnlinePayment = async () => {
        const token = sessionStorage.getItem(tokenKey);
        const invoice = selectedPayableInvoice;
        if (!token) {
            redirectToSignIn();
            return;
        }
        if (!invoice) return;

        const requestKey = invoice.id + ':' + invoice.outstanding_amount;
        let idempotencyKey = paymentKeys.current.get(requestKey);
        if (!idempotencyKey) {
            idempotencyKey = createIdempotencyKey('portal-payment');
            paymentKeys.current.set(requestKey, idempotencyKey);
        }
        setPaymentBusy(true);
        setPaymentMessage(null);
        setPaymentMessageError(true);
        try {
            const payload = await fetchPortalJson<{
                payload?: { client_secret?: unknown; publishable_key?: unknown };
            }>('/api/v1/portal/' + tenant.slug + '/payments/intent', token, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify({ invoice_id: invoice.id, amount: invoice.outstanding_amount }),
            });
            const clientSecret = payload.payload?.client_secret;
            const publishableKey = payload.payload?.publishable_key;
            if (typeof clientSecret !== 'string' || typeof publishableKey !== 'string') {
                setPaymentMessage(t('portal.dashboard.incomplete_checkout'));
                return;
            }

            setPaymentIntent({ clientSecret, publishableKey, invoiceId: invoice.id, requestKey });
        } catch (requestError) {
            if (isDefinitivePortalFailure(requestError)) {
                paymentKeys.current.delete(requestKey);
            }
            if (!handleUnauthorized(requestError)) {
                setPaymentMessage(portalErrorMessage(requestError, t, 'portal.dashboard.payment_start_error'));
            }
        } finally {
            setPaymentBusy(false);
        }
    };

    const refreshInvoiceHistory = async () => {
        const cursors = [...invoicePageCursors.current];
        if (!(await loadInvoicePage())) return;
        for (const cursor of cursors) {
            if (!(await loadInvoicePage(cursor, true))) return;
        }
    };

    const refreshPaymentHistory = async () => {
        const cursors = [...paymentPageCursors.current];
        if (!(await loadPaymentPage())) return;
        for (const cursor of cursors) {
            if (!(await loadPaymentPage(cursor, true))) return;
        }
    };

    const paymentSubmitted = async () => {
        if (paymentIntent) {
            paymentKeys.current.delete(paymentIntent.requestKey);
        }
        setPaymentMessage(t('portal.dashboard.payment_submitted'));
        setPaymentMessageError(false);
        setPaymentIntent(null);
        await Promise.all([loadCustomer(), loadBalance(), refreshInvoiceHistory(), refreshPaymentHistory()]);
    };

    const submitTicket = async (event: React.FormEvent) => {
        event.preventDefault();
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }
        if (!ticketForm.subject.trim() || !ticketForm.description.trim()) return;
        setTicketBusy(true);
        setError(null);
        setSupportMessage(null);
        try {
            await fetchPortalResponse('/api/v1/portal/' + tenant.slug + '/me/tickets', token, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(ticketForm),
            });
            setTicketForm({ category: 'other', subject: '', description: '' });
            setSupportMessage(t('portal.dashboard.ticket_sent'));
            await loadTickets();
        } catch (requestError) {
            if (!handleUnauthorized(requestError)) {
                setError(portalErrorMessage(requestError, t, 'portal.dashboard.ticket_error'));
            }
        } finally {
            setTicketBusy(false);
        }
    };

    const rateTicket = async (ticketId: string, rating: number) => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }
        if (rating < 1 || rating > 5) return;
        setRatingBusy(ticketId);
        setError(null);
        setSupportMessage(null);
        try {
            const payload = await fetchPortalJson<{ data: { satisfaction_rating: number } }>(
                '/api/v1/portal/' + tenant.slug + '/me/tickets/' + encodeURIComponent(ticketId) + '/rating',
                token,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rating }),
                },
            );
            setTickets((current) =>
                current.map((ticket) =>
                    ticket.uuid === ticketId
                        ? { ...ticket, satisfaction_rating: payload.data.satisfaction_rating }
                        : ticket,
                ),
            );
            setSupportMessage(t('portal.dashboard.rating_thanks'));
        } catch (requestError) {
            if (!handleUnauthorized(requestError)) {
                setError(portalErrorMessage(requestError, t, 'portal.dashboard.rating_error'));
            }
        } finally {
            setRatingBusy(null);
        }
    };

    const loadInvoiceDetail = async (invoiceId: string) => {
        setInvoiceDetailError(null);
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }

        setInvoiceDetailBusy(invoiceId);
        try {
            const detail = await fetchPortalJson<PortalInvoiceDetail>(
                '/api/v1/portal/' + tenant.slug + '/me/invoices/' + encodeURIComponent(invoiceId),
                token,
            );
            setInvoiceDetails((current) => ({ ...current, [invoiceId]: detail }));
        } catch (requestError) {
            if (!handleUnauthorized(requestError)) {
                setInvoiceDetailError(portalErrorMessage(requestError, t, 'portal.dashboard.invoice_detail_error'));
            }
        } finally {
            setInvoiceDetailBusy(null);
        }
    };

    const toggleInvoiceDetails = (invoiceId: string) => {
        if (openInvoiceId === invoiceId) {
            setOpenInvoiceId(null);
            return;
        }

        setOpenInvoiceId(invoiceId);
        setInvoiceDetailError(null);
        if (!invoiceDetails[invoiceId]) {
            void loadInvoiceDetail(invoiceId);
        }
    };

    const downloadInvoice = async (invoice: PortalInvoice) => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            redirectToSignIn();
            return;
        }

        setDownloadBusyId(invoice.id);
        setError(null);
        try {
            const response = await fetchPortalResponse(
                '/api/v1/portal/' + tenant.slug + '/me/invoices/' + encodeURIComponent(invoice.id) + '/pdf',
                token,
            );
            if (!response.headers.get('content-type')?.toLowerCase().includes('application/pdf')) {
                throw new PortalRequestError();
            }

            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = invoice.number + '.pdf';
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (requestError) {
            if (!handleUnauthorized(requestError)) {
                setError(portalErrorMessage(requestError, t, 'portal.dashboard.invoice_download_error'));
            }
        } finally {
            setDownloadBusyId(null);
        }
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-8 text-ink">
            <Head title={t('portal.dashboard.title')} />
            <main className="mx-auto max-w-3xl">
                <header className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center overflow-hidden rounded-xl bg-brand text-white">
                            {tenant.logo_url ? (
                                <img src={tenant.logo_url} alt="" className="size-full object-cover" />
                            ) : (
                                <Wifi size={19} />
                            )}
                        </div>
                        <div>
                            <p className="font-display font-bold">{tenant.name}</p>
                            <p className="text-sm text-muted">{t('portal.dashboard.customer_portal')}</p>
                        </div>
                    </div>
                    <button type="button" onClick={signOut} className="button-secondary">
                        <LogOut size={16} />
                        {t('portal.dashboard.sign_out')}
                    </button>
                </header>
                {error && (
                    <p className="mt-8 field-error" role="alert">
                        {error}
                    </p>
                )}
                {!customer && (
                    <section className="card mt-8 space-y-3 p-6">
                        <p role={loadState.customer.loading ? 'status' : 'alert'}>
                            {loadState.customer.loading
                                ? t('portal.dashboard.loading')
                                : t('portal.dashboard.load_error')}
                        </p>
                        {loadState.customer.error && (
                            <button
                                type="button"
                                disabled={loadState.customer.loading}
                                onClick={() => void loadCustomer()}
                                className="button-secondary"
                            >
                                {t('portal.dashboard.retry')}
                            </button>
                        )}
                    </section>
                )}
                {customer && (
                    <>
                        <div className="mt-12">
                            <p className="eyebrow">{t('portal.dashboard.welcome_back')}</p>
                            <h1 className="page-title">
                                {customer.first_name} {customer.last_name ?? ''}
                            </h1>
                            <p className="page-subtitle">{t('portal.dashboard.subtitle')}</p>
                        </div>
                        <section
                            className="mt-8 grid gap-4 sm:grid-cols-2"
                            aria-label={t('portal.dashboard.account_summary')}
                        >
                            <div className="card p-6">
                                <p className="eyebrow">{t('portal.dashboard.current_balance')}</p>
                                <p
                                    className={`mt-3 text-3xl font-semibold ${customer.balance_amount > 0 ? 'text-rose-700' : 'text-ink'}`}
                                >
                                    {formatMoney(customer.balance_amount, customer.balance_currency)}
                                </p>
                                <p className="mt-2 text-sm text-muted">
                                    {loadState.balance.loading && !balance
                                        ? t('portal.dashboard.loading')
                                        : balance?.next_due
                                          ? `${t('portal.dashboard.next_due')} ${formatDate(balance.next_due.due_at)}`
                                          : loadState.balance.error
                                            ? t('portal.dashboard.load_error')
                                            : t('portal.dashboard.no_outstanding_balance')}
                                </p>
                                {loadState.balance.error && (
                                    <button
                                        type="button"
                                        onClick={() => void loadBalance()}
                                        className="button-secondary mt-3"
                                    >
                                        {t('portal.dashboard.retry')}
                                    </button>
                                )}
                            </div>
                            <div className="card p-6">
                                <p className="eyebrow">{t('portal.dashboard.account')}</p>
                                <p className="mt-3 text-3xl font-semibold">{customer.services.length}</p>
                                <p className="mt-2 text-sm text-muted">
                                    {customer.services.length === 1
                                        ? t('portal.dashboard.active_connection')
                                        : t('portal.dashboard.connections_linked')}
                                </p>
                            </div>
                        </section>
                        {(notices.length > 0 || loadState.notices.loading || loadState.notices.error) && (
                            <section className="mt-8 space-y-3" aria-labelledby="notices-heading">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle size={17} className="text-amber-600" />
                                    <h2 id="notices-heading" className="section-title">
                                        {t('portal.dashboard.service_notices')}
                                    </h2>
                                </div>
                                {loadState.notices.loading && (
                                    <p className="text-sm text-muted" role="status">
                                        {t('portal.dashboard.loading')}
                                    </p>
                                )}
                                {loadState.notices.error && (
                                    <div className="flex flex-wrap items-center gap-3" role="alert">
                                        <p className="text-sm text-muted">{t('portal.dashboard.load_error')}</p>
                                        <button
                                            type="button"
                                            onClick={() => void loadNotices()}
                                            className="button-secondary"
                                        >
                                            {t('portal.dashboard.retry')}
                                        </button>
                                    </div>
                                )}
                                {notices.map((notice) => (
                                    <article
                                        key={notice.uuid}
                                        className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950"
                                    >
                                        <p className="text-xs font-bold uppercase tracking-[0.16em]">
                                            {enumLabel(notice.severity, t)}
                                        </p>
                                        <h3 className="mt-1 font-semibold">{notice.title}</h3>
                                        {notice.description && (
                                            <p className="mt-1 text-sm text-amber-900/80">{notice.description}</p>
                                        )}
                                    </article>
                                ))}
                            </section>
                        )}
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
                                                <p className="mt-1 text-sm text-muted">
                                                    {service.plan.download_kbps / 1000} Mbps {t('downstream')} ·{' '}
                                                    {service.plan.upload_kbps / 1000} Mbps {t('upstream')}
                                                </p>
                                            </div>
                                        </div>
                                        <StatusBadge status={service.status} />
                                    </div>
                                    <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-[1fr_auto] sm:items-center">
                                        <div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted">
                                                    {t('portal.dashboard.usage_this_period')}
                                                </span>
                                                <span className="font-semibold">
                                                    {Math.round((service.usage.used_bytes / 1_000_000_000) * 10) / 10} /{' '}
                                                    {service.usage.quota_bytes > 0
                                                        ? Math.round((service.usage.quota_bytes / 1_000_000_000) * 10) /
                                                          10
                                                        : '∞'}{' '}
                                                    GB
                                                </span>
                                            </div>
                                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-sand">
                                                <div
                                                    className="h-full rounded-full bg-brand"
                                                    style={{
                                                        width: `${Math.min(100, service.usage.quota_bytes > 0 ? (service.usage.used_bytes / service.usage.quota_bytes) * 100 : 0)}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="mt-2 text-sm text-muted">
                                                {t('portal.dashboard.expires')} {formatDate(service.expires_at)}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3 sm:justify-end">
                                            <span className="inline-flex items-center gap-1.5 text-sm text-muted">
                                                <RefreshCw size={14} />
                                                {enumLabel(service.network_state, t)}
                                            </span>
                                            {service.status === 'active' && (
                                                <div className="flex flex-col items-start gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={restartBusy === service.public_id}
                                                        onClick={() => void restartService(service.public_id)}
                                                        className="button-secondary"
                                                    >
                                                        <RefreshCw size={15} />
                                                        {restartBusy === service.public_id
                                                            ? t('portal.dashboard.restarting')
                                                            : t('portal.dashboard.restart_connection')}
                                                    </button>
                                                    {restartRequestedId === service.public_id && (
                                                        <p className="text-sm text-muted" role="status">
                                                            {t('portal.dashboard.restart_requested')}
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="card p-10 text-center">
                                    <p className="font-semibold">{t('portal.dashboard.no_services')}</p>
                                    <p className="mt-1 text-sm text-muted">{t('portal.dashboard.contact_provider')}</p>
                                </div>
                            )}
                        </section>
                        <section className="mt-8 grid gap-6 md:grid-cols-2">
                            <div className="card p-6">
                                <h2 className="section-title">{t('portal.dashboard.invoices')}</h2>
                                {loadState.invoices.loading && invoices.length === 0 && (
                                    <p className="mt-4 text-sm text-muted" role="status">
                                        {t('portal.dashboard.loading')}
                                    </p>
                                )}
                                {loadState.invoices.error && (
                                    <div className="mt-4 flex flex-wrap items-center gap-3" role="alert">
                                        <p className="text-sm text-muted">{t('portal.dashboard.load_error')}</p>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                void loadInvoicePage(invoiceCursor ?? undefined, invoiceCursor !== null)
                                            }
                                            className="button-secondary"
                                        >
                                            {t('portal.dashboard.retry')}
                                        </button>
                                    </div>
                                )}
                                <div className="mt-4 divide-y divide-line">
                                    {invoices.map((invoice) => {
                                        const isOpen = openInvoiceId === invoice.id;
                                        const detail = invoiceDetails[invoice.id];
                                        const invoiceStatus =
                                            invoice.status === 'issued'
                                                ? invoice.outstanding_amount > 0
                                                    ? t('portal.dashboard.outstanding')
                                                    : t('portal.dashboard.paid')
                                                : enumLabel(invoice.status, t);

                                        return (
                                            <div key={invoice.id} className="py-4">
                                                <div className="grid gap-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                                    <span>
                                                        <b>{invoice.number}</b>
                                                        <small className="mt-1 block text-muted">{invoiceStatus}</small>
                                                        <small className="mt-1 block text-muted">
                                                            {invoice.due_at
                                                                ? t('public.billing.due') +
                                                                  ' ' +
                                                                  formatDate(invoice.due_at)
                                                                : t('portal.dashboard.no_due_date')}
                                                        </small>
                                                    </span>
                                                    <span className="font-semibold sm:text-end">
                                                        {formatMoney(invoice.total_amount, invoice.currency)}
                                                        <small className="mt-1 block font-normal text-muted">
                                                            {t('public.billing.outstanding')}:{' '}
                                                            {formatMoney(invoice.outstanding_amount, invoice.currency)}
                                                        </small>
                                                    </span>
                                                    <div className="flex flex-wrap gap-2 sm:col-span-2 sm:justify-end">
                                                        <button
                                                            type="button"
                                                            aria-expanded={isOpen}
                                                            aria-controls={'invoice-detail-' + invoice.id}
                                                            onClick={() => toggleInvoiceDetails(invoice.id)}
                                                            className="button-secondary"
                                                        >
                                                            {isOpen ? (
                                                                <ChevronUp size={15} />
                                                            ) : (
                                                                <ChevronDown size={15} />
                                                            )}
                                                            {isOpen
                                                                ? t('portal.dashboard.hide_details')
                                                                : t('portal.dashboard.view_details')}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={downloadBusyId === invoice.id}
                                                            onClick={() => void downloadInvoice(invoice)}
                                                            className="button-secondary"
                                                        >
                                                            <Download size={15} />
                                                            {downloadBusyId === invoice.id
                                                                ? t('portal.dashboard.downloading')
                                                                : t('portal.dashboard.download_invoice')}
                                                        </button>
                                                    </div>
                                                </div>
                                                {isOpen && (
                                                    <div
                                                        id={'invoice-detail-' + invoice.id}
                                                        className="mt-4 rounded-xl bg-sand p-4 text-sm"
                                                    >
                                                        {invoiceDetailBusy === invoice.id && (
                                                            <p role="status">{t('portal.dashboard.loading')}</p>
                                                        )}
                                                        {invoiceDetailError && (
                                                            <div
                                                                className="flex flex-wrap items-center gap-3"
                                                                role="alert"
                                                            >
                                                                <p>{invoiceDetailError}</p>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => void loadInvoiceDetail(invoice.id)}
                                                                    className="button-secondary"
                                                                >
                                                                    {t('portal.dashboard.retry')}
                                                                </button>
                                                            </div>
                                                        )}
                                                        {detail && (
                                                            <>
                                                                <ul className="divide-y divide-line">
                                                                    {detail.lines.map((line, index) => (
                                                                        <li
                                                                            key={invoice.id + '-line-' + index}
                                                                            className="flex items-start justify-between gap-4 py-2"
                                                                        >
                                                                            <span>{line.description}</span>
                                                                            <span className="font-medium">
                                                                                {formatMoney(
                                                                                    line.amount,
                                                                                    line.currency,
                                                                                )}
                                                                            </span>
                                                                        </li>
                                                                    ))}
                                                                </ul>
                                                                <dl className="mt-3 grid gap-2 border-t border-line pt-3 sm:max-w-sm sm:ms-auto">
                                                                    <div className="flex justify-between gap-4">
                                                                        <dt>{t('public.billing.subtotal')}</dt>
                                                                        <dd>
                                                                            {formatMoney(
                                                                                detail.subtotal_amount,
                                                                                detail.currency,
                                                                            )}
                                                                        </dd>
                                                                    </div>
                                                                    <div className="flex justify-between gap-4">
                                                                        <dt>{t('public.billing.tax')}</dt>
                                                                        <dd>
                                                                            {formatMoney(
                                                                                detail.tax_amount,
                                                                                detail.currency,
                                                                            )}
                                                                        </dd>
                                                                    </div>
                                                                    <div className="flex justify-between gap-4 font-semibold">
                                                                        <dt>{t('public.billing.total')}</dt>
                                                                        <dd>
                                                                            {formatMoney(
                                                                                detail.total_amount,
                                                                                detail.currency,
                                                                            )}
                                                                        </dd>
                                                                    </div>
                                                                </dl>
                                                                {detail.payments.length > 0 && (
                                                                    <div className="mt-4 border-t border-line pt-3">
                                                                        <h3 className="font-semibold">
                                                                            {t('portal.dashboard.payment_history')}
                                                                        </h3>
                                                                        {detail.payments.map((payment) => (
                                                                            <p
                                                                                key={payment.id}
                                                                                className="mt-2 flex justify-between gap-4"
                                                                            >
                                                                                <span>
                                                                                    {payment.number} ·{' '}
                                                                                    {enumLabel(payment.status, t)}
                                                                                </span>
                                                                                <span>
                                                                                    {formatMoney(
                                                                                        payment.amount,
                                                                                        payment.currency,
                                                                                    )}
                                                                                </span>
                                                                            </p>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                    {!loadState.invoices.loading &&
                                        !loadState.invoices.error &&
                                        invoices.length === 0 && (
                                            <p className="py-3 text-sm text-muted">
                                                {t('portal.dashboard.no_invoices')}
                                            </p>
                                        )}
                                </div>
                                {invoiceCursor && (
                                    <button
                                        type="button"
                                        disabled={loadState.invoices.loading}
                                        onClick={() => void loadInvoicePage(invoiceCursor, true)}
                                        className="button-secondary mt-4"
                                    >
                                        {loadState.invoices.loading
                                            ? t('portal.dashboard.loading_older')
                                            : t('portal.dashboard.load_older')}
                                    </button>
                                )}
                            </div>
                            <div className="card p-6">
                                <h2 className="section-title">{t('portal.dashboard.payment_history')}</h2>
                                {loadState.payments.loading && payments.length === 0 && (
                                    <p className="mt-4 text-sm text-muted" role="status">
                                        {t('portal.dashboard.loading')}
                                    </p>
                                )}
                                {loadState.payments.error && (
                                    <div className="mt-4 flex flex-wrap items-center gap-3" role="alert">
                                        <p className="text-sm text-muted">{t('portal.dashboard.load_error')}</p>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                void loadPaymentPage(paymentCursor ?? undefined, paymentCursor !== null)
                                            }
                                            className="button-secondary"
                                        >
                                            {t('portal.dashboard.retry')}
                                        </button>
                                    </div>
                                )}
                                <div className="mt-4 divide-y divide-line">
                                    {payments.map((payment) => (
                                        <div
                                            key={payment.id}
                                            className="flex items-center justify-between gap-4 py-3 text-sm"
                                        >
                                            <span>
                                                <b>{payment.number}</b>
                                                <small className="mt-1 block text-muted">
                                                    {enumLabel(payment.status, t)}
                                                </small>
                                                <small className="mt-1 block text-muted">
                                                    {payment.received_at ? formatDate(payment.received_at) : '—'}
                                                </small>
                                            </span>
                                            <span className="font-semibold">
                                                {formatMoney(payment.amount, payment.currency)}
                                            </span>
                                        </div>
                                    ))}
                                    {!loadState.payments.loading &&
                                        !loadState.payments.error &&
                                        payments.length === 0 && (
                                            <p className="py-3 text-sm text-muted">
                                                {t('portal.dashboard.no_payments')}
                                            </p>
                                        )}
                                </div>
                                {paymentCursor && (
                                    <button
                                        type="button"
                                        disabled={loadState.payments.loading}
                                        onClick={() => void loadPaymentPage(paymentCursor, true)}
                                        className="button-secondary mt-4"
                                    >
                                        {loadState.payments.loading
                                            ? t('portal.dashboard.loading_older')
                                            : t('portal.dashboard.load_older')}
                                    </button>
                                )}
                            </div>
                        </section>
                        {loadState.billing.loading && !billing && (
                            <p className="mt-4 text-sm text-muted" role="status">
                                {t('portal.dashboard.loading')}
                            </p>
                        )}
                        {loadState.billing.error && (
                            <div className="mt-4 flex flex-wrap items-center gap-3" role="alert">
                                <p className="text-sm text-muted">{t('portal.dashboard.load_error')}</p>
                                <button type="button" onClick={() => void loadBilling()} className="button-secondary">
                                    {t('portal.dashboard.retry')}
                                </button>
                            </div>
                        )}
                        {billing?.online_payments.enabled &&
                            invoices.some(
                                (invoice) => invoice.status === 'issued' && invoice.outstanding_amount > 0,
                            ) && (
                                <section className="card mt-8 p-6" aria-labelledby="online-payment-heading">
                                    <div className="flex items-center gap-2">
                                        <CreditCard size={17} className="text-brand" />
                                        <h2 id="online-payment-heading" className="section-title">
                                            {t('portal.dashboard.pay_invoice')}
                                        </h2>
                                    </div>
                                    <p className="mt-2 text-sm text-muted">
                                        {t('portal.dashboard.payment_confirmation_note')}
                                    </p>
                                    {!paymentIntent ? (
                                        <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                                            <label className="block flex-1">
                                                <span className="field-label">{t('portal.dashboard.invoice')}</span>
                                                <ResponsiveSelect
                                                    className="field"
                                                    value={selectedPayableInvoice?.id ?? ''}
                                                    onChange={(event) => {
                                                        setSelectedInvoiceId(event.target.value);
                                                        setPaymentMessage(null);
                                                        setPaymentMessageError(false);
                                                    }}
                                                >
                                                    {invoices
                                                        .filter(
                                                            (invoice) =>
                                                                invoice.status === 'issued' &&
                                                                invoice.outstanding_amount > 0,
                                                        )
                                                        .map((invoice) => (
                                                            <option key={invoice.id} value={invoice.id}>
                                                                {invoice.number} ·{' '}
                                                                {formatMoney(
                                                                    invoice.outstanding_amount,
                                                                    invoice.currency,
                                                                )}
                                                            </option>
                                                        ))}
                                                </ResponsiveSelect>
                                            </label>
                                            <button
                                                type="button"
                                                disabled={paymentBusy}
                                                onClick={startOnlinePayment}
                                                className="button-primary"
                                            >
                                                <CreditCard size={16} />
                                                {paymentBusy
                                                    ? t('portal.dashboard.opening_checkout')
                                                    : t('portal.dashboard.continue_payment')}
                                            </button>
                                        </div>
                                    ) : (
                                        <StripeCheckout
                                            clientSecret={paymentIntent.clientSecret}
                                            publishableKey={paymentIntent.publishableKey}
                                            t={t}
                                            onSubmitted={paymentSubmitted}
                                            onError={(message) => {
                                                setPaymentMessage(message);
                                                setPaymentMessageError(true);
                                            }}
                                        />
                                    )}
                                    {paymentMessage && (
                                        <p
                                            className="mt-4 text-sm text-muted"
                                            role={paymentMessageError ? 'alert' : 'status'}
                                        >
                                            {paymentMessage}
                                        </p>
                                    )}
                                </section>
                            )}
                        <form
                            onSubmit={saveProfile}
                            className="card mt-8 space-y-5 p-6"
                            aria-labelledby="profile-heading"
                        >
                            <div className="flex items-center gap-2">
                                <UserRound size={17} className="text-brand" />
                                <h2 id="profile-heading" className="section-title">
                                    {t('portal.dashboard.contact_details')}
                                </h2>
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="block">
                                    <span className="field-label">{t('Email')}</span>
                                    <input
                                        type="email"
                                        className="field"
                                        value={profileForm.email}
                                        onChange={(event) =>
                                            setProfileForm({ ...profileForm, email: event.target.value })
                                        }
                                    />
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('Address')}</span>
                                    <input
                                        className="field"
                                        value={profileForm.address}
                                        onChange={(event) =>
                                            setProfileForm({ ...profileForm, address: event.target.value })
                                        }
                                    />
                                </label>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <p className="text-sm text-muted">
                                    {t('Phone')}: {customer.phone}
                                </p>
                                <button type="submit" disabled={profileBusy} className="button-primary">
                                    {profileSaved ? <Check size={16} /> : null}
                                    {profileBusy
                                        ? t('portal.dashboard.saving')
                                        : profileSaved
                                          ? t('portal.dashboard.saved')
                                          : t('portal.dashboard.save_details')}
                                </button>
                            </div>
                        </form>
                        <section className="mt-8 grid gap-6 md:grid-cols-[1fr_0.9fr]" aria-labelledby="support-heading">
                            <div className="card p-6">
                                <h2 id="support-heading" className="section-title">
                                    {t('portal.dashboard.support_tickets')}
                                </h2>
                                {loadState.tickets.loading && tickets.length === 0 && (
                                    <p className="mt-4 text-sm text-muted" role="status">
                                        {t('portal.dashboard.loading')}
                                    </p>
                                )}
                                {loadState.tickets.error && (
                                    <div className="mt-4 flex flex-wrap items-center gap-3" role="alert">
                                        <p className="text-sm text-muted">{t('portal.dashboard.load_error')}</p>
                                        <button
                                            type="button"
                                            onClick={() => void loadTickets()}
                                            className="button-secondary"
                                        >
                                            {t('portal.dashboard.retry')}
                                        </button>
                                    </div>
                                )}
                                <div className="mt-4 divide-y divide-line">
                                    {tickets.map((ticket) => (
                                        <div key={ticket.uuid} className="space-y-3 py-3 text-sm">
                                            <div className="flex items-center justify-between gap-4">
                                                <span>
                                                    <b>{ticket.subject}</b>
                                                    <small className="mt-1 block text-muted">
                                                        {ticket.number} · {enumLabel(ticket.status, t)}
                                                    </small>
                                                </span>
                                                <span className="text-xs text-muted">
                                                    {ticket.message_count} {t('portal.dashboard.messages')}
                                                </span>
                                            </div>
                                            {(ticket.status === 'resolved' || ticket.status === 'closed') && (
                                                <label className="block max-w-xs">
                                                    <span className="field-label">
                                                        {t('portal.dashboard.rate_support')}
                                                    </span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={ticket.satisfaction_rating?.toString() ?? ''}
                                                        disabled={ratingBusy === ticket.uuid}
                                                        onChange={(event) =>
                                                            rateTicket(ticket.uuid, Number(event.target.value))
                                                        }
                                                    >
                                                        <option value="">{t('portal.dashboard.choose_rating')}</option>
                                                        {[1, 2, 3, 4, 5].map((rating) => (
                                                            <option key={rating} value={rating}>
                                                                {rating}/5
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                </label>
                                            )}
                                        </div>
                                    ))}
                                    {!loadState.tickets.loading && !loadState.tickets.error && tickets.length === 0 && (
                                        <p className="py-3 text-sm text-muted">{t('portal.dashboard.no_tickets')}</p>
                                    )}
                                </div>
                                {supportMessage && <p className="mt-3 text-sm text-brand">{supportMessage}</p>}
                            </div>
                            <form onSubmit={submitTicket} className="card space-y-4 p-6">
                                <h2 className="section-title">{t('portal.dashboard.open_ticket')}</h2>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.category')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={ticketForm.category}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, category: event.target.value })
                                        }
                                    >
                                        <option value="no_service">{t('portal.category.no_service')}</option>
                                        <option value="slow">{t('portal.category.slow')}</option>
                                        <option value="billing">{t('portal.category.billing')}</option>
                                        <option value="relocation">{t('portal.category.relocation')}</option>
                                        <option value="other">{t('portal.category.other')}</option>
                                    </ResponsiveSelect>
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.subject')}</span>
                                    <input
                                        required
                                        className="field"
                                        value={ticketForm.subject}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, subject: event.target.value })
                                        }
                                    />
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.what_happened')}</span>
                                    <textarea
                                        required
                                        rows={4}
                                        className="field"
                                        value={ticketForm.description}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, description: event.target.value })
                                        }
                                    />
                                </label>
                                <button
                                    type="submit"
                                    disabled={ticketBusy}
                                    className="button-primary w-full justify-center"
                                >
                                    <Send size={16} />
                                    {ticketBusy ? t('portal.dashboard.sending') : t('portal.dashboard.send_ticket')}
                                </button>
                            </form>
                        </section>
                    </>
                )}
            </main>
            <Link href={`/portal/${tenant.slug}`} className="sr-only">
                {t('portal.dashboard.return_to_sign_in')}
            </Link>
        </div>
    );
}

function StripeCheckout({
    clientSecret,
    publishableKey,
    t,
    onSubmitted,
    onError,
}: {
    clientSecret: string;
    publishableKey: string;
    t: (key: string) => string;
    onSubmitted: () => Promise<void>;
    onError: (message: string) => void;
}) {
    const [stripeUi, setStripeUi] = useState<typeof import('@stripe/react-stripe-js') | null>(null);
    const [stripePromise, setStripePromise] = useState<ReturnType<
        typeof import('@stripe/stripe-js').loadStripe
    > | null>(null);
    const [stripeLoadError, setStripeLoadError] = useState(false);

    useEffect(() => {
        let active = true;
        Promise.all([import('@stripe/react-stripe-js'), import('@stripe/stripe-js')])
            .then(([ui, stripe]) => {
                if (!active) return;
                setStripeUi(ui);
                setStripePromise(stripe.loadStripe(publishableKey));
            })
            .catch(() => {
                if (active) setStripeLoadError(true);
            });

        return () => {
            active = false;
        };
    }, [publishableKey]);

    if (stripeLoadError) {
        return (
            <p className="mt-5 text-sm text-muted" role="alert">
                {t('portal.dashboard.payment_confirm_error')}
            </p>
        );
    }

    if (!stripeUi || !stripePromise) {
        return (
            <p className="mt-5 text-sm text-muted" role="status">
                {t('portal.dashboard.opening_checkout')}
            </p>
        );
    }

    const { Elements } = stripeUi;

    return (
        <div className="mt-5 rounded-2xl border border-line p-4">
            <Elements stripe={stripePromise} options={{ clientSecret }}>
                <StripePaymentForm stripeUi={stripeUi} t={t} onSubmitted={onSubmitted} onError={onError} />
            </Elements>
        </div>
    );
}

function StripePaymentForm({
    stripeUi,
    t,
    onSubmitted,
    onError,
}: {
    stripeUi: typeof import('@stripe/react-stripe-js');
    t: (key: string) => string;
    onSubmitted: () => Promise<void>;
    onError: (message: string) => void;
}) {
    const stripe = stripeUi.useStripe();
    const elements = stripeUi.useElements();
    const [busy, setBusy] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!stripe || !elements) return;
        setBusy(true);
        try {
            const result = await stripe.confirmPayment({
                elements,
                confirmParams: { return_url: window.location.href },
                redirect: 'if_required',
            });
            if (result.error) {
                onError(result.error.message ? t(result.error.message) : t('portal.dashboard.payment_confirm_error'));
            } else {
                await onSubmitted();
            }
        } catch {
            onError(t('portal.dashboard.payment_confirm_error'));
        } finally {
            setBusy(false);
        }
    };

    const { PaymentElement } = stripeUi;

    return (
        <form onSubmit={submit} className="space-y-4">
            <PaymentElement />
            <button
                type="submit"
                disabled={busy || !stripe || !elements}
                className="button-primary w-full justify-center"
            >
                <CreditCard size={16} />
                {busy ? t('portal.dashboard.confirming_payment') : t('portal.dashboard.pay_securely')}
            </button>
        </form>
    );
}

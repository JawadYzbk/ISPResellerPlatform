import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    CloudOff,
    CreditCard,
    LogIn,
    LogOut,
    MapPin,
    RefreshCw,
    Search,
    UserRound,
    Wifi,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import {
    clearFieldState,
    readFieldState,
    writeFieldState,
    type CachedFieldSnapshot,
    type FieldCustomerCache,
    type QueuedFieldPayment,
} from '@/lib/field-store';
import { currencyFractionDigits, entriesOrEmpty, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { createIdempotencyKey } from '@/lib/idempotency';

type FieldCustomer = FieldCustomerCache;

type FieldSnapshot = {
    sync_token: string;
    generated_at: string;
    data: {
        customers: FieldCustomer[];
        services: unknown[];
        plans: unknown[];
        exchange_rates: unknown[];
        message_templates: unknown[];
    };
    tombstones?: { customers?: string[] };
};

type CollectorShift = {
    id: string;
    status: string;
    opened_at: string | null;
    opening_float: Record<string, number>;
    system_totals: Record<string, number>;
    payment_count: number;
} | null;

type CollectorSummary = {
    date: string;
    payment_count: number;
    totals: Record<string, number>;
};

type FieldDay = {
    id: string;
    status: 'active' | 'completed';
    checked_in_at: string;
    checked_out_at: string | null;
    check_in: { latitude: number; longitude: number; accuracy_meters: number | null };
    check_out: { latitude: number; longitude: number; accuracy_meters: number | null } | null;
} | null;

type SyncResult = {
    index: number;
    status: 'created' | 'replayed' | 'rejected' | 'error';
    error?: string;
};

type Props = {
    snapshot: FieldSnapshot;
    shift: CollectorShift;
    summary: CollectorSummary;
    fieldDay: FieldDay;
    currencies: CurrencyOption[];
    defaultCurrency: string;
    storageKey: string;
    storageEncryptionKey: string;
};

function newIdempotencyKey(): string {
    return createIdempotencyKey('field');
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function FieldIndex({
    snapshot,
    shift,
    summary,
    fieldDay: initialFieldDay,
    currencies,
    defaultCurrency,
    storageKey,
    storageEncryptionKey,
}: Props) {
    const [customers, setCustomers] = useState(snapshot.data.customers);
    const [currencyOptions, setCurrencyOptions] = useState(currencies);
    const [selectedCustomerId, setSelectedCustomerId] = useState('');
    const [search, setSearch] = useState('');
    const [amount, setAmount] = useState('');
    const [currency, setCurrency] = useState(defaultCurrency || currencies[0]?.code || 'USD');
    const [method, setMethod] = useState('cash');
    const [pending, setPending] = useState<QueuedFieldPayment[]>([]);
    const [syncToken, setSyncToken] = useState(snapshot.sync_token);
    const [online, setOnline] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine));
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [hydrated, setHydrated] = useState(false);
    const [fieldDay, setFieldDay] = useState<FieldDay>(initialFieldDay);
    const [locationBusy, setLocationBusy] = useState(false);

    const selectedCustomer = customers.find((customer) => customer.id === selectedCustomerId) ?? null;
    const selectedCurrency = currencyOptions.find((item) => item.code === currency);
    const fractionDigits = selectedCurrency?.decimal_digits ?? currencyFractionDigits(currency);
    const filteredCustomers = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (query === '') return customers;

        return customers.filter((customer) =>
            `${customer.code} ${customer.first_name} ${customer.last_name ?? ''} ${customer.phone}`
                .toLowerCase()
                .includes(query),
        );
    }, [customers, search]);

    const persist = useCallback(
        async (
            nextPending: QueuedFieldPayment[],
            nextSyncToken = syncToken,
            nextCustomers = customers,
            nextCurrencies = currencyOptions,
        ) => {
            setPending(nextPending);
            setSyncToken(nextSyncToken);
            await writeFieldState(
                {
                    key: storageKey,
                    pending: nextPending,
                    sync_token: nextSyncToken,
                    cached_snapshot: {
                        sync_token: nextSyncToken,
                        generated_at: new Date().toISOString(),
                        customers: nextCustomers,
                        currencies: nextCurrencies,
                        default_currency: defaultCurrency,
                    },
                },
                storageEncryptionKey,
            );
        },
        [currencyOptions, customers, defaultCurrency, storageEncryptionKey, storageKey, syncToken],
    );

    const refreshSnapshot = useCallback(async () => {
        if (!online) {
            setError('You are offline. The saved customer list and payment queue are still available.');
            return;
        }

        setBusy(true);
        setError(null);
        try {
            const query = syncToken ? `?since=${encodeURIComponent(syncToken)}` : '';
            const response = await fetch(`/field/sync${query}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = (await response.json()) as FieldSnapshot;
            if (!response.ok) throw new Error('The field list could not be refreshed.');

            const changedCustomers = new Map(customers.map((customer) => [customer.id, customer]));
            body.data.customers.forEach((customer) => changedCustomers.set(customer.id, customer));
            (body.tombstones?.customers ?? []).forEach((customerId) => changedCustomers.delete(customerId));
            const nextCustomers = [...changedCustomers.values()];
            setCustomers(nextCustomers);
            await persist(pending, body.sync_token, nextCustomers);
            setMessage('Field data refreshed.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The field list could not be refreshed.');
        } finally {
            setBusy(false);
        }
    }, [customers, online, pending, persist, syncToken]);

    const pushQueue = useCallback(
        async (queue = pending) => {
            if (!online || queue.length === 0) return;

            setBusy(true);
            setError(null);
            try {
                const response = await fetch('/field/push', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        items: queue.map((item) => {
                            const cleanItem = { ...item };
                            delete cleanItem.last_error;
                            return cleanItem;
                        }),
                    }),
                });
                const body = (await response.json()) as { results?: SyncResult[] };
                if (!response.ok || !Array.isArray(body.results))
                    throw new Error('Queued payments could not be synchronized.');

                const results = body.results;
                const remaining = queue.flatMap((item, index) => {
                    const result = results.find((candidate) => candidate.index === index);
                    return result?.status === 'created' || result?.status === 'replayed'
                        ? []
                        : [{ ...item, last_error: result?.error ?? 'Payment was not accepted yet.' }];
                });
                await persist(remaining);
                setMessage(
                    remaining.length === 0
                        ? 'All queued payments were synchronized.'
                        : 'Some payments remain queued for review.',
                );
            } catch (caught) {
                setError(caught instanceof Error ? caught.message : 'Queued payments could not be synchronized.');
            } finally {
                setBusy(false);
            }
        },
        [online, pending, persist],
    );

    useEffect(() => {
        let mounted = true;
        void readFieldState(storageKey, storageEncryptionKey).then(async (stored) => {
            if (!mounted) return;

            const cached = stored?.cached_snapshot;
            const hasServerSnapshot = snapshot.sync_token !== '' || snapshot.generated_at !== '';
            const nextCustomers = hasServerSnapshot ? snapshot.data.customers : (cached?.customers ?? []);
            const nextCurrencies = currencies.length > 0 ? currencies : (cached?.currencies ?? []);
            const nextSyncToken = hasServerSnapshot
                ? snapshot.sync_token
                : (cached?.sync_token ?? stored?.sync_token ?? '');

            setPending(stored?.pending ?? []);
            setCustomers(nextCustomers);
            setCurrencyOptions(nextCurrencies);
            setSyncToken(nextSyncToken);
            if (!defaultCurrency && cached?.default_currency) setCurrency(cached.default_currency);

            if (!stored || hasServerSnapshot || cached) {
                const cachedSnapshot: CachedFieldSnapshot = {
                    sync_token: nextSyncToken,
                    generated_at: hasServerSnapshot
                        ? snapshot.generated_at
                        : (cached?.generated_at ?? new Date().toISOString()),
                    customers: nextCustomers,
                    currencies: nextCurrencies,
                    default_currency: defaultCurrency || cached?.default_currency || nextCurrencies[0]?.code || 'USD',
                };
                await writeFieldState(
                    {
                        key: storageKey,
                        pending: stored?.pending ?? [],
                        sync_token: nextSyncToken || null,
                        cached_snapshot: cachedSnapshot,
                    },
                    storageEncryptionKey,
                );
            }

            setHydrated(true);
        });

        const markOnline = () => setOnline(true);
        const markOffline = () => setOnline(false);
        window.addEventListener('online', markOnline);
        window.addEventListener('offline', markOffline);

        return () => {
            mounted = false;
            window.removeEventListener('online', markOnline);
            window.removeEventListener('offline', markOffline);
        };
    }, [currencies, defaultCurrency, snapshot, storageEncryptionKey, storageKey]);

    const wasOnline = useRef(online);
    const initialSyncStarted = useRef(false);

    useEffect(() => {
        const becameOnline = online && !wasOnline.current;
        wasOnline.current = online;
        if (!hydrated || !online || pending.length === 0) return;
        if (!becameOnline && initialSyncStarted.current) return;

        initialSyncStarted.current = true;

        const timer = window.setTimeout(() => void pushQueue(), 0);
        return () => window.clearTimeout(timer);
    }, [hydrated, online, pending.length, pushQueue]);

    const queuePayment = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedCustomer) {
            setError('Choose a customer before saving a payment.');
            return;
        }

        const amountMinor = parseMoneyToMinor(amount, currency);
        if (amountMinor === null) {
            setError('Enter a valid positive amount.');
            return;
        }

        const item: QueuedFieldPayment = {
            customer_id: selectedCustomer.id,
            amount: amountMinor,
            currency,
            method,
            idempotency_key: newIdempotencyKey(),
        };
        await persist([...pending, item]);
        setAmount('');
        setMessage(
            online
                ? 'Payment saved locally and queued for synchronization.'
                : 'Payment saved on this device. It will synchronize when you are back online.',
        );
        setError(null);
        if (online) void pushQueue([...pending, item]);
    };

    const clearDeviceData = async () => {
        await clearFieldState(storageKey);
        setPending([]);
        setSyncToken(snapshot.sync_token);
        setCustomers(snapshot.data.customers);
        setCurrencyOptions(currencies);
        setMessage('Field data was cleared from this device.');
        setError(null);
    };

    const updateFieldDay = async (action: 'check-in' | 'check-out') => {
        if (!online) {
            setError('Connect to the internet before recording a field check-in.');
            return;
        }
        if (!('geolocation' in navigator)) {
            setError('Location capture is not available in this browser.');
            return;
        }

        setLocationBusy(true);
        setError(null);
        setMessage(null);
        try {
            const position = await new Promise<GeolocationPosition>((resolve, reject) =>
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                }),
            );
            const response = await fetch(`/field/${action}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy_meters: Math.round(position.coords.accuracy),
                }),
            });
            const body = (await response.json()) as { message?: string; data?: Exclude<FieldDay, null> };
            if (!response.ok || !body.data) throw new Error(body.message ?? 'The field check-in could not be saved.');

            setFieldDay(action === 'check-out' ? null : body.data);
            setMessage(body.message ?? (action === 'check-in' ? 'Field day started.' : 'Field day ended.'));
        } catch (caught) {
            if (typeof caught === 'object' && caught !== null && 'code' in caught) {
                const locationError = caught as GeolocationPositionError;
                setError(
                    locationError.code === locationError.PERMISSION_DENIED
                        ? 'Location permission is required for field check-in.'
                        : 'A reliable location could not be captured. Move to an open area and try again.',
                );
            } else {
                setError(caught instanceof Error ? caught.message : 'The field check-in could not be saved.');
            }
        } finally {
            setLocationBusy(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Field collection" />
            <div className="mx-auto max-w-4xl pb-24">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="eyebrow">Field operations</p>
                        <h1 className="page-title">Collector desk</h1>
                        <p className="page-subtitle">
                            Find customers, record collections and keep working when the connection drops.
                        </p>
                    </div>
                    <div
                        className={`inline-flex items-center gap-2 self-start rounded-full px-3 py-2 text-xs font-semibold ${online ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
                    >
                        {online ? <Wifi size={15} /> : <CloudOff size={15} />}
                        {online ? 'Online' : 'Offline'}
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="card p-4">
                        <p className="eyebrow">Field day</p>
                        <p className="mt-2 text-lg font-semibold">{fieldDay ? 'Checked in' : 'Not started'}</p>
                        <p className="mt-1 text-xs text-muted">
                            {fieldDay
                                ? 'Location recorded for this field session.'
                                : 'Start when you begin your route.'}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">Shift</p>
                        <p className="mt-2 text-lg font-semibold">{shift ? 'Open' : 'Not open'}</p>
                        <p className="mt-1 text-xs text-muted">
                            {shift
                                ? `${shift.payment_count} posted payment(s)`
                                : 'Open a cash shift before collecting.'}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">Today</p>
                        <p className="mt-2 text-lg font-semibold">{summary.payment_count} payment(s)</p>
                        <p className="mt-1 text-xs text-muted">
                            {entriesOrEmpty(summary.totals)
                                .map(([code, value]) => formatMoney(value, code))
                                .join(' · ') || 'Nothing posted yet'}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">Queue</p>
                        <p className="mt-2 text-lg font-semibold">{pending.length} pending</p>
                        <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 text-xs font-semibold text-brand disabled:opacity-50"
                                disabled={!online || busy || pending.length === 0}
                                onClick={() => void pushQueue()}
                            >
                                <RefreshCw size={13} className={busy ? 'animate-spin' : ''} /> Synchronize now
                            </button>
                            <ConfirmDialog
                                title="Clear field data from this device?"
                                description={
                                    pending.length > 0
                                        ? 'This removes the cached customer list and permanently deletes the queued payments on this device. Only continue after the queue is synchronized or no longer needed.'
                                        : 'This removes the cached customer list and currency catalog from this device. The server data is not changed.'
                                }
                                confirmLabel="Clear device data"
                                destructive={pending.length > 0}
                                onConfirm={clearDeviceData}
                            >
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-coral disabled:opacity-50"
                                    disabled={busy}
                                >
                                    Clear device data
                                </button>
                            </ConfirmDialog>
                        </div>
                    </div>
                </div>

                <section className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <MapPin className="mt-0.5 shrink-0 text-brand" size={19} />
                        <div>
                            <h2 className="text-sm font-semibold">Field attendance</h2>
                            <p className="mt-1 text-pretty text-xs text-muted">
                                Your browser shares location only when you press this button. There is no continuous
                                background tracking.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className={fieldDay ? 'button-secondary shrink-0' : 'button-primary shrink-0'}
                        disabled={locationBusy || !online}
                        onClick={() => void updateFieldDay(fieldDay ? 'check-out' : 'check-in')}
                    >
                        {fieldDay ? <LogOut size={16} /> : <LogIn size={16} />}
                        {locationBusy ? 'Capturing location…' : fieldDay ? 'Finish field day' : 'Start field day'}
                    </button>
                </section>

                {!shift && (
                    <div className="mt-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>Open a cash shift before recording collector payments.</span>
                        <Link href="/billing/shifts" className="font-semibold underline">
                            Open shifts
                        </Link>
                    </div>
                )}

                {(message || error) && (
                    <div
                        className={`mt-6 rounded-xl border p-4 text-sm ${error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                        role="status"
                    >
                        {error ?? message}
                    </div>
                )}

                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line p-5">
                        <p className="eyebrow">Customer queue</p>
                        <div className="flex items-start justify-between gap-4">
                            <h2 className="mt-1 text-xl font-semibold">Choose a customer</h2>
                            <button
                                type="button"
                                className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-brand disabled:opacity-50"
                                onClick={() => void refreshSnapshot()}
                                disabled={!online || busy}
                            >
                                <RefreshCw size={13} className={busy ? 'animate-spin' : ''} /> Refresh list
                            </button>
                        </div>
                        <div className="relative mt-4">
                            <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                            <input
                                className="field ps-10"
                                aria-label="Search field customers"
                                placeholder="Search name, code or phone"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </div>
                    </div>
                    <div className="max-h-80 divide-y divide-line overflow-y-auto">
                        {filteredCustomers.map((customer) => {
                            const selected = customer.id === selectedCustomerId;
                            return (
                                <button
                                    key={customer.id}
                                    type="button"
                                    data-testid="field-customer-row"
                                    className={`flex w-full items-center gap-3 px-5 py-4 text-start transition ${selected ? 'bg-brand-soft' : 'hover:bg-sand'}`}
                                    onClick={() => setSelectedCustomerId(customer.id)}
                                >
                                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-sand text-brand">
                                        <UserRound size={18} />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-semibold">
                                            {customer.first_name} {customer.last_name ?? ''}
                                        </span>
                                        <span className="mt-1 block truncate text-xs text-muted">
                                            {customer.code} · {customer.phone}
                                        </span>
                                    </span>
                                    <span className="text-end text-sm font-semibold">
                                        {formatMoney(customer.balance_amount, customer.balance_currency)}
                                    </span>
                                </button>
                            );
                        })}
                        {filteredCustomers.length === 0 && (
                            <p className="p-6 text-sm text-muted">No customers match this search.</p>
                        )}
                    </div>
                </section>

                <form className="card mt-6 space-y-5 p-5" onSubmit={(event) => void queuePayment(event)}>
                    <div className="flex items-center gap-2">
                        <CreditCard size={18} className="text-brand" />
                        <div>
                            <p className="font-semibold">Record collection</p>
                            <p className="text-xs text-muted">
                                {selectedCustomer
                                    ? `${selectedCustomer.first_name} ${selectedCustomer.last_name ?? ''}`
                                    : 'Select a customer above'}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className="block">
                            <span className="field-label">Amount ({currency})</span>
                            <input
                                className="field"
                                aria-label="Field payment amount"
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step={fractionDigits === 0 ? '1' : '0.01'}
                                placeholder={fractionDigits === 0 ? '0' : '0.00'}
                                value={amount}
                                onChange={(event) => setAmount(event.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="field-label">Currency</span>
                            <CurrencyCombobox
                                aria-label="Field payment currency"
                                value={currency}
                                currencies={currencyOptions}
                                onChange={setCurrency}
                            />
                        </label>
                        <label className="block sm:col-span-2">
                            <span className="field-label">Payment method</span>
                            <ResponsiveSelect
                                aria-label="Field payment method"
                                value={method}
                                onChange={(event) => setMethod(event.target.value)}
                            >
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="card">Card</option>
                                <option value="mobile_wallet">Mobile wallet</option>
                            </ResponsiveSelect>
                        </label>
                    </div>
                    <button
                        type="submit"
                        className="button-primary w-full justify-center"
                        disabled={!shift || !selectedCustomer || busy}
                    >
                        <CheckCircle2 size={17} /> Save payment to device
                    </button>
                    <p className="text-xs text-muted">
                        Payments use a unique idempotency key and are encrypted in this browser until the server accepts
                        or rejects them.
                    </p>
                </form>

                {pending.length > 0 && (
                    <section className="card mt-6 p-5">
                        <h2 className="font-semibold">Pending review</h2>
                        <div className="mt-3 space-y-3">
                            {pending.map((item) => {
                                const customer = customers.find((candidate) => candidate.id === item.customer_id);
                                return (
                                    <div
                                        key={item.idempotency_key}
                                        className="rounded-lg border border-line bg-sand px-4 py-3 text-sm"
                                    >
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="font-semibold">
                                                {customer?.first_name ?? item.customer_id}
                                            </span>
                                            <span className="font-semibold">
                                                {formatMoney(item.amount, item.currency)}
                                            </span>
                                        </div>
                                        {item.last_error && (
                                            <p className="mt-1 text-xs text-rose-700">{item.last_error}</p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

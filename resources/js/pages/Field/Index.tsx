import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDollarSign,
    ClipboardCheck,
    CloudOff,
    CreditCard,
    LogIn,
    LogOut,
    ListOrdered,
    LocateFixed,
    MapPin,
    MessageSquare,
    Paperclip,
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

type FieldRouteStop = {
    id: string;
    position: number;
    outcome: 'pending' | 'collected' | 'no_answer' | 'refused' | 'reschedule' | 'address_issue';
    note: string | null;
    visited_at: string | null;
    customer: {
        id: string;
        code: string;
        name: string;
        phone: string | null;
        address: string | null;
        latitude: number | null;
        longitude: number | null;
        zone: string | null;
        balance_amount: number;
        balance_currency: string;
        next_expires_at: string | null;
    };
};

type FieldRoute = {
    id: string;
    route_date: string;
    status: 'planned' | 'in_progress' | 'completed';
    stop_count: number;
    completed_count: number;
    stops: FieldRouteStop[];
} | null;

type FieldTask = {
    id: string;
    title: string;
    description: string | null;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    status: 'assigned' | 'acknowledged' | 'in_progress' | 'completed' | 'cancelled';
    due_at: string | null;
    unread: boolean;
    customer: { id: string; code: string; name: string; phone: string | null; address: string | null } | null;
    messages: {
        id: string;
        body: string;
        created_at: string;
        author: { id: number; name: string; role: string; is_viewer: boolean };
        attachments: { id: string; name: string; mime_type: string; size_bytes: number; download_url: string }[];
    }[];
};

type FieldCustodyEntry = {
    id: string;
    type: 'advance' | 'expense' | 'handover' | 'adjustment';
    direction: 'credit' | 'debit';
    status: 'pending' | 'posted' | 'rejected';
    amount: number;
    currency: string;
    description: string;
    reference: string | null;
    occurred_at: string;
    review_note: string | null;
};

type FieldCustody = {
    position: { balances: Record<string, number>; cash_payment_count: number; pending_count: number };
    entries: FieldCustodyEntry[];
};

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
    route: FieldRoute;
    tasks: FieldTask[];
    custody: FieldCustody;
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

function distanceMeters(origin: { latitude: number; longitude: number }, stop: FieldRouteStop): number {
    if (stop.customer.latitude === null || stop.customer.longitude === null) return Number.POSITIVE_INFINITY;
    const radians = (degrees: number) => (degrees * Math.PI) / 180;
    const earthRadius = 6_371_000;
    const latitudeDelta = radians(stop.customer.latitude - origin.latitude);
    const longitudeDelta = radians(stop.customer.longitude - origin.longitude);
    const firstLatitude = radians(origin.latitude);
    const secondLatitude = radians(stop.customer.latitude);
    const value =
        Math.sin(latitudeDelta / 2) ** 2 +
        Math.cos(firstLatitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;

    return earthRadius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
}

export default function FieldIndex({
    snapshot,
    shift,
    summary,
    fieldDay: initialFieldDay,
    route: initialRoute,
    tasks: initialTasks,
    custody: initialCustody,
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
    const [checkoutNote, setCheckoutNote] = useState('');
    const [collectorRoute, setCollectorRoute] = useState<FieldRoute>(initialRoute);
    const [routeOrigin, setRouteOrigin] = useState<{ latitude: number; longitude: number } | null>(null);
    const [nearbyOrder, setNearbyOrder] = useState(false);
    const [selectedStopId, setSelectedStopId] = useState('');
    const [visitOutcome, setVisitOutcome] = useState<FieldRouteStop['outcome']>('no_answer');
    const [visitNote, setVisitNote] = useState('');
    const [visitBusy, setVisitBusy] = useState(false);
    const [tasks, setTasks] = useState(initialTasks);
    const [selectedTaskId, setSelectedTaskId] = useState(
        initialTasks.find((task) => task.unread)?.id ?? initialTasks[0]?.id ?? '',
    );
    const [taskReply, setTaskReply] = useState('');
    const [taskAttachment, setTaskAttachment] = useState<File | null>(null);
    const [taskBusy, setTaskBusy] = useState(false);
    const [custody, setCustody] = useState(initialCustody);
    const [custodyType, setCustodyType] = useState<'expense' | 'handover'>('expense');
    const [custodyAmount, setCustodyAmount] = useState('');
    const [custodyCurrency, setCustodyCurrency] = useState(defaultCurrency || currencies[0]?.code || 'USD');
    const [custodyDescription, setCustodyDescription] = useState('');
    const [custodyReference, setCustodyReference] = useState('');
    const [custodyBusy, setCustodyBusy] = useState(false);

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
    const orderedRouteStops = useMemo(() => {
        if (!collectorRoute) return [];
        const stops = [...collectorRoute.stops];
        return nearbyOrder && routeOrigin
            ? stops.sort((left, right) => distanceMeters(routeOrigin, left) - distanceMeters(routeOrigin, right))
            : stops.sort((left, right) => left.position - right.position);
    }, [collectorRoute, nearbyOrder, routeOrigin]);
    const selectedTask = tasks.find((task) => task.id === selectedTaskId) ?? null;

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
                    summary_note: action === 'check-out' ? checkoutNote : undefined,
                }),
            });
            const body = (await response.json()) as { message?: string; data?: Exclude<FieldDay, null> };
            if (!response.ok || !body.data) throw new Error(body.message ?? 'The field check-in could not be saved.');

            setFieldDay(action === 'check-out' ? null : body.data);
            if (action === 'check-out') setCheckoutNote('');
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

    const captureLocation = () =>
        new Promise<GeolocationPosition>((resolve, reject) =>
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0,
            }),
        );

    const sortRouteNearby = async () => {
        if (!('geolocation' in navigator)) {
            setError('Location capture is not available in this browser.');
            return;
        }
        setLocationBusy(true);
        setError(null);
        try {
            const position = await captureLocation();
            setRouteOrigin({ latitude: position.coords.latitude, longitude: position.coords.longitude });
            setNearbyOrder(true);
            setMessage('Route ordered by your current location. The manager’s planned order is unchanged.');
        } catch {
            setError('A reliable location could not be captured for nearby sorting.');
        } finally {
            setLocationBusy(false);
        }
    };

    const recordVisit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!fieldDay) {
            setError('Start your field day before recording a visit.');
            return;
        }
        if (!selectedStopId || !('geolocation' in navigator)) return;

        setVisitBusy(true);
        setError(null);
        try {
            const position = await captureLocation();
            const response = await fetch(`/field/route-stops/${selectedStopId}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    outcome: visitOutcome,
                    note: visitNote || null,
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy_meters: Math.round(position.coords.accuracy),
                }),
            });
            const body = (await response.json()) as { message?: string; data?: Exclude<FieldRoute, null> };
            if (!response.ok || !body.data) throw new Error(body.message ?? 'The visit outcome could not be saved.');

            setCollectorRoute(body.data);
            setSelectedStopId('');
            setVisitNote('');
            setMessage(body.message ?? 'Visit outcome recorded.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The visit outcome could not be saved.');
        } finally {
            setVisitBusy(false);
        }
    };

    const replaceTask = (updated: FieldTask) => {
        setTasks((current) => current.map((task) => (task.id === updated.id ? updated : task)));
    };

    const openTask = async (task: FieldTask) => {
        setSelectedTaskId(task.id);
        if (!task.unread || !online) return;
        try {
            const response = await fetch(`/field/tasks/${task.id}/read`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            if (response.ok) replaceTask({ ...task, unread: false });
        } catch {
            // The task remains readable even if the acknowledgement cannot be synchronized.
        }
    };

    const updateTaskStatus = async (task: FieldTask) => {
        const nextStatus =
            task.status === 'assigned' ? 'acknowledged' : task.status === 'acknowledged' ? 'in_progress' : 'completed';
        setTaskBusy(true);
        setError(null);
        try {
            const response = await fetch(`/field/tasks/${task.id}/status`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ status: nextStatus }),
            });
            const body = (await response.json()) as { message?: string; data?: FieldTask };
            if (!response.ok || !body.data) throw new Error(body.message ?? 'The task could not be updated.');
            if (body.data.status === 'completed' || body.data.status === 'cancelled') {
                const remaining = tasks.filter((item) => item.id !== body.data?.id);
                setTasks(remaining);
                setSelectedTaskId(remaining[0]?.id ?? '');
            } else {
                replaceTask(body.data);
            }
            setMessage(body.message ?? 'Task updated.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The task could not be updated.');
        } finally {
            setTaskBusy(false);
        }
    };

    const sendTaskReply = async (task: FieldTask) => {
        if (taskReply.trim() === '') return;
        setTaskBusy(true);
        setError(null);
        try {
            const payload = new FormData();
            payload.append('body', taskReply);
            if (taskAttachment) payload.append('attachment', taskAttachment);
            const response = await fetch(`/field/tasks/${task.id}/messages`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: payload,
            });
            const body = (await response.json()) as { message?: string; data?: FieldTask };
            if (!response.ok || !body.data) throw new Error(body.message ?? 'The message could not be sent.');
            replaceTask(body.data);
            setTaskReply('');
            setTaskAttachment(null);
            setMessage(body.message ?? 'Message sent.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The message could not be sent.');
        } finally {
            setTaskBusy(false);
        }
    };

    const submitCustodyRequest = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!online) {
            setError('Connect to the internet before submitting a custody request.');
            return;
        }
        const amount = parseMoneyToMinor(custodyAmount, custodyCurrency);
        if (amount === null || amount <= 0) {
            setError('Enter a positive custody amount.');
            return;
        }
        if (custodyDescription.trim() === '') {
            setError('Describe the expense or handover before submitting.');
            return;
        }

        setCustodyBusy(true);
        setError(null);
        try {
            const response = await fetch('/field/custody', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    type: custodyType,
                    amount,
                    currency: custodyCurrency,
                    description: custodyDescription,
                    reference: custodyReference || null,
                }),
            });
            const body = (await response.json()) as {
                message?: string;
                data?: { entry: FieldCustodyEntry; position: FieldCustody['position'] };
            };
            if (!response.ok || !body.data)
                throw new Error(body.message ?? 'The custody request could not be submitted.');
            setCustody((current) => ({
                position: body.data!.position,
                entries: [body.data!.entry, ...current.entries],
            }));
            setCustodyAmount('');
            setCustodyDescription('');
            setCustodyReference('');
            setMessage(body.message ?? 'Custody request submitted.');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The custody request could not be submitted.');
        } finally {
            setCustodyBusy(false);
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

                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line p-5">
                        <div className="flex items-start gap-3">
                            <CircleDollarSign className="mt-0.5 shrink-0 text-brand" size={20} />
                            <div>
                                <h2 className="text-balance text-xl font-semibold">Cash custody</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    Cash collections and opening float stay in custody until an expense or handover is
                                    approved.
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-3">
                            {entriesOrEmpty(custody.position.balances).map(([currencyCode, amount]) => (
                                <div key={currencyCode} className="rounded-xl border border-line px-4 py-3">
                                    <p className="eyebrow">{currencyCode} balance</p>
                                    <p className={`mt-1 font-semibold tabular-nums ${amount < 0 ? 'text-coral' : ''}`}>
                                        {formatMoney(amount, currencyCode)}
                                    </p>
                                </div>
                            ))}
                            <div className="rounded-xl border border-line px-4 py-3">
                                <p className="eyebrow">Pending review</p>
                                <p className="mt-1 font-semibold tabular-nums">{custody.position.pending_count}</p>
                            </div>
                        </div>
                    </div>
                    <form
                        className="grid gap-4 p-5 sm:grid-cols-2"
                        onSubmit={(event) => void submitCustodyRequest(event)}
                    >
                        <label className="field-label">
                            Request type
                            <ResponsiveSelect
                                className="mt-1"
                                value={custodyType}
                                onChange={(event) => setCustodyType(event.target.value as 'expense' | 'handover')}
                            >
                                <option value="expense">Field expense</option>
                                <option value="handover">Cash handover</option>
                            </ResponsiveSelect>
                        </label>
                        <label className="field-label">
                            Currency
                            <CurrencyCombobox
                                className="field mt-1"
                                value={custodyCurrency}
                                currencies={currencyOptions}
                                onChange={setCustodyCurrency}
                            />
                        </label>
                        <label className="field-label">
                            Amount
                            <input
                                className="field mt-1 tabular-nums"
                                inputMode="decimal"
                                value={custodyAmount}
                                onChange={(event) => setCustodyAmount(event.target.value)}
                            />
                        </label>
                        <label className="field-label">
                            Reference (optional)
                            <input
                                className="field mt-1"
                                value={custodyReference}
                                maxLength={120}
                                placeholder="Receipt or handover reference"
                                onChange={(event) => setCustodyReference(event.target.value)}
                            />
                        </label>
                        <label className="field-label sm:col-span-2">
                            Description
                            <textarea
                                className="field mt-1 min-h-20"
                                value={custodyDescription}
                                maxLength={2000}
                                placeholder={
                                    custodyType === 'expense'
                                        ? 'What was purchased and why?'
                                        : 'Who received the cash and where?'
                                }
                                onChange={(event) => setCustodyDescription(event.target.value)}
                            />
                        </label>
                        <div className="flex justify-end sm:col-span-2">
                            <button
                                className="button-primary"
                                disabled={!online || custodyBusy || custodyDescription.trim() === ''}
                            >
                                {custodyBusy
                                    ? 'Submitting…'
                                    : custodyType === 'expense'
                                      ? 'Submit expense'
                                      : 'Submit handover'}
                            </button>
                        </div>
                    </form>
                    {custody.entries.length > 0 && (
                        <div className="border-t border-line">
                            <div className="px-5 py-3">
                                <p className="eyebrow">Recent custody activity</p>
                            </div>
                            <div className="divide-y divide-line">
                                {custody.entries.slice(0, 6).map((entry) => (
                                    <div key={entry.id} className="flex items-start justify-between gap-4 px-5 py-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-semibold capitalize">{entry.type}</p>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${entry.status === 'posted' ? 'bg-emerald-50 text-emerald-700' : entry.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'}`}
                                                >
                                                    {entry.status}
                                                </span>
                                            </div>
                                            <p className="mt-1 line-clamp-2 text-xs text-muted">{entry.description}</p>
                                            {entry.review_note && (
                                                <p className="mt-1 text-xs text-muted">Manager: {entry.review_note}</p>
                                            )}
                                        </div>
                                        <p
                                            className={`shrink-0 text-sm font-semibold tabular-nums ${entry.direction === 'debit' ? 'text-coral' : 'text-emerald-700'}`}
                                        >
                                            {entry.direction === 'debit' ? '−' : '+'}
                                            {formatMoney(entry.amount, entry.currency)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>

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
                    {fieldDay && (
                        <label className="field-label min-w-0 flex-1 sm:max-w-sm">
                            Checkout note (optional)
                            <textarea
                                className="field mt-1 min-h-16"
                                value={checkoutNote}
                                maxLength={2000}
                                placeholder="Cash handover, unresolved visits, or follow-up needed"
                                onChange={(event) => setCheckoutNote(event.target.value)}
                            />
                        </label>
                    )}
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

                <section className="card mt-6 overflow-hidden">
                    <div className="flex items-start justify-between gap-4 border-b border-line p-5">
                        <div className="flex items-start gap-3">
                            <ClipboardCheck className="mt-0.5 shrink-0 text-brand" size={19} />
                            <div>
                                <h2 className="text-balance text-xl font-semibold">Assigned tasks</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    Acknowledge field work and keep questions with the assignment.
                                </p>
                            </div>
                        </div>
                        <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand tabular-nums">
                            {tasks.length} open
                        </span>
                    </div>
                    {tasks.length > 0 ? (
                        <div className="grid md:grid-cols-[minmax(14rem,0.8fr)_minmax(0,1.2fr)]">
                            <div className="divide-y divide-line border-b border-line md:border-e md:border-b-0">
                                {tasks.map((task) => (
                                    <button
                                        key={task.id}
                                        type="button"
                                        className={`block w-full p-4 text-start ${selectedTaskId === task.id ? 'bg-brand-soft/60' : 'hover:bg-sand/40'}`}
                                        onClick={() => void openTask(task)}
                                    >
                                        <div className="flex items-start gap-2">
                                            <span className="min-w-0 flex-1 line-clamp-2 text-sm font-semibold">
                                                {task.title}
                                            </span>
                                            {task.unread && (
                                                <span
                                                    className="mt-1.5 size-2 shrink-0 rounded-full bg-brand"
                                                    aria-label="Unread messages"
                                                />
                                            )}
                                        </div>
                                        <p className="mt-2 text-xs capitalize text-muted">
                                            {task.priority} · {task.status.replaceAll('_', ' ')}
                                        </p>
                                    </button>
                                ))}
                            </div>
                            {selectedTask && (
                                <div className="min-w-0 p-5">
                                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                        <div>
                                            <p className="eyebrow">{selectedTask.priority} priority</p>
                                            <h3 className="mt-1 text-balance text-lg font-semibold">
                                                {selectedTask.title}
                                            </h3>
                                        </div>
                                        <button
                                            type="button"
                                            className="button-primary shrink-0"
                                            disabled={
                                                !online ||
                                                taskBusy ||
                                                selectedTask.status === 'completed' ||
                                                selectedTask.status === 'cancelled'
                                            }
                                            onClick={() => void updateTaskStatus(selectedTask)}
                                        >
                                            {selectedTask.status === 'assigned'
                                                ? 'Acknowledge'
                                                : selectedTask.status === 'acknowledged'
                                                  ? 'Start task'
                                                  : selectedTask.status === 'in_progress'
                                                    ? 'Complete task'
                                                    : 'Completed'}
                                        </button>
                                    </div>
                                    {selectedTask.description && (
                                        <p className="mt-4 whitespace-pre-wrap text-pretty text-sm text-muted">
                                            {selectedTask.description}
                                        </p>
                                    )}
                                    {selectedTask.customer && (
                                        <Link
                                            href={`/customers/${selectedTask.customer.id}`}
                                            className="mt-4 block rounded-xl border border-line p-4"
                                        >
                                            <p className="text-sm font-semibold text-brand">
                                                {selectedTask.customer.name} · {selectedTask.customer.code}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {selectedTask.customer.address ??
                                                    selectedTask.customer.phone ??
                                                    'Open customer details'}
                                            </p>
                                        </Link>
                                    )}
                                    <div className="mt-5 border-t border-line pt-5">
                                        <div className="flex items-center gap-2">
                                            <MessageSquare size={17} className="text-brand" />
                                            <h4 className="text-sm font-semibold">Conversation</h4>
                                        </div>
                                        <div className="mt-3 max-h-72 space-y-3 overflow-y-auto">
                                            {selectedTask.messages.map((taskMessage) => (
                                                <div
                                                    key={taskMessage.id}
                                                    className={`max-w-[90%] rounded-xl border border-line p-3 ${taskMessage.author.is_viewer ? 'ms-auto bg-brand-soft/60' : 'bg-sand/40'}`}
                                                >
                                                    <p className="text-xs font-semibold">{taskMessage.author.name}</p>
                                                    <p className="mt-1 whitespace-pre-wrap text-pretty text-sm">
                                                        {taskMessage.body}
                                                    </p>
                                                    {taskMessage.attachments.map((attachment) => (
                                                        <a
                                                            key={attachment.id}
                                                            href={attachment.download_url}
                                                            className="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-brand"
                                                            download
                                                        >
                                                            <Paperclip size={13} /> {attachment.name}
                                                        </a>
                                                    ))}
                                                </div>
                                            ))}
                                            {selectedTask.messages.length === 0 && (
                                                <p className="rounded-xl border border-dashed border-line p-5 text-center text-sm text-muted">
                                                    No messages yet.
                                                </p>
                                            )}
                                        </div>
                                        <form
                                            className="mt-4"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                void sendTaskReply(selectedTask);
                                            }}
                                        >
                                            <label className="field-label">
                                                Reply
                                                <textarea
                                                    className="field mt-1 min-h-20"
                                                    value={taskReply}
                                                    maxLength={5000}
                                                    onChange={(event) => setTaskReply(event.target.value)}
                                                />
                                            </label>
                                            <label className="field-label mt-3 block">
                                                Attachment (optional)
                                                <input
                                                    key={taskAttachment?.name ?? 'empty'}
                                                    className="field mt-1"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                                    onChange={(event) =>
                                                        setTaskAttachment(event.target.files?.[0] ?? null)
                                                    }
                                                />
                                            </label>
                                            <div className="mt-3 flex justify-end">
                                                <button
                                                    className="button-secondary"
                                                    disabled={!online || taskBusy || taskReply.trim() === ''}
                                                >
                                                    Send reply
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="p-10 text-center">
                            <CheckCircle2 className="mx-auto text-emerald-600" size={28} />
                            <p className="mt-3 font-semibold">No open tasks</p>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                New assignments from your manager will appear here.
                            </p>
                        </div>
                    )}
                </section>

                <section className="card mt-6 overflow-hidden">
                    <div className="flex flex-col gap-4 border-b border-line p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="eyebrow">Today’s route</p>
                            <h2 className="mt-1 text-xl font-semibold text-balance">
                                {collectorRoute
                                    ? `${collectorRoute.completed_count}/${collectorRoute.stop_count} stops completed`
                                    : 'No route assigned'}
                            </h2>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                Planned order is preserved. Nearby sorting changes only your current view.
                            </p>
                        </div>
                        {collectorRoute && (
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    className={nearbyOrder ? 'button-secondary' : 'button-primary'}
                                    onClick={() => void sortRouteNearby()}
                                    disabled={locationBusy}
                                >
                                    <LocateFixed size={15} /> Nearest first
                                </button>
                                <button
                                    type="button"
                                    className={!nearbyOrder ? 'button-secondary' : 'button-quiet'}
                                    onClick={() => setNearbyOrder(false)}
                                >
                                    <ListOrdered size={15} /> Planned order
                                </button>
                            </div>
                        )}
                    </div>
                    {collectorRoute ? (
                        <div className="divide-y divide-line">
                            {orderedRouteStops.map((stop, visibleIndex) => {
                                const distance = routeOrigin ? distanceMeters(routeOrigin, stop) : null;
                                const selected = selectedStopId === stop.id;
                                return (
                                    <div key={stop.id} className="p-5">
                                        <div className="flex items-start gap-3">
                                            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-soft text-xs font-bold tabular-nums text-brand">
                                                {nearbyOrder ? visibleIndex + 1 : stop.position}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <p className="font-semibold">{stop.customer.name}</p>
                                                    <span className="text-xs text-muted">{stop.customer.code}</span>
                                                    {stop.outcome !== 'pending' && (
                                                        <span className="rounded-full bg-sand px-2 py-1 text-xs font-semibold capitalize text-muted">
                                                            {stop.outcome.replaceAll('_', ' ')}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-pretty text-sm text-muted">
                                                    {stop.customer.address ?? stop.customer.zone ?? 'No address saved'}
                                                </p>
                                                <p className="mt-2 text-xs font-semibold tabular-nums text-muted">
                                                    {formatMoney(
                                                        stop.customer.balance_amount,
                                                        stop.customer.balance_currency,
                                                    )}
                                                    {distance !== null && Number.isFinite(distance)
                                                        ? ` · ${distance < 1000 ? `${Math.round(distance)} m` : `${(distance / 1000).toFixed(1)} km`}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 flex-col gap-2 sm:flex-row">
                                                <button
                                                    type="button"
                                                    className="button-secondary"
                                                    onClick={() => {
                                                        setSelectedCustomerId(stop.customer.id);
                                                        document
                                                            .getElementById('field-payment-form')
                                                            ?.scrollIntoView({ block: 'start' });
                                                    }}
                                                >
                                                    Collect
                                                </button>
                                                <button
                                                    type="button"
                                                    className="button-primary"
                                                    onClick={() => setSelectedStopId(selected ? '' : stop.id)}
                                                    disabled={!fieldDay || stop.outcome !== 'pending'}
                                                >
                                                    Outcome
                                                </button>
                                            </div>
                                        </div>
                                        {selected && (
                                            <form
                                                onSubmit={recordVisit}
                                                className="mt-4 grid gap-4 rounded-xl border border-line bg-sand/50 p-4 sm:grid-cols-[0.7fr_1.3fr_auto] sm:items-end"
                                            >
                                                <label>
                                                    <span className="field-label">Visit outcome</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={visitOutcome}
                                                        onChange={(event) =>
                                                            setVisitOutcome(
                                                                event.target.value as FieldRouteStop['outcome'],
                                                            )
                                                        }
                                                    >
                                                        <option value="collected">Collected</option>
                                                        <option value="no_answer">No answer</option>
                                                        <option value="refused">Refused</option>
                                                        <option value="reschedule">Reschedule</option>
                                                        <option value="address_issue">Address issue</option>
                                                    </ResponsiveSelect>
                                                </label>
                                                <label>
                                                    <span className="field-label">Visit note</span>
                                                    <input
                                                        className="field"
                                                        value={visitNote}
                                                        onChange={(event) => setVisitNote(event.target.value)}
                                                        placeholder="Optional operational note"
                                                    />
                                                </label>
                                                <button className="button-primary" disabled={visitBusy}>
                                                    {visitBusy ? 'Saving…' : 'Save outcome'}
                                                </button>
                                            </form>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="p-10 text-center">
                            <MapPin className="mx-auto text-muted" size={28} />
                            <p className="mt-3 font-semibold">No customer stops assigned today</p>
                            <p className="mt-1 text-sm text-muted">Your manager can publish a route from operations.</p>
                        </div>
                    )}
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

                <section id="field-payment-form" className="card mt-6 overflow-hidden scroll-mt-24">
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

import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import CustomerCombobox from '@/components/ui/customer-combobox';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';
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

type FieldStock = {
    locations: {
        id: number;
        code: string;
        name: string;
        balances: { item_id: number; sku: string; name: string; quantity: string }[];
    }[];
    central_locations: { id: number; code: string; name: string }[];
    items: { id: number; sku: string; name: string }[];
    requests: {
        id: string;
        type: 'replenishment' | 'return';
        status: 'pending' | 'approved' | 'rejected';
        quantity: string;
        note: string | null;
        review_note: string | null;
        item: { sku: string; name: string } | null;
        source: { code: string; name: string } | null;
        destination: { code: string; name: string } | null;
    }[];
    counts: {
        id: string;
        status: 'pending' | 'posted' | 'rejected';
        note: string | null;
        review_note: string | null;
        warehouse: { code: string; name: string } | null;
        variance: { item: { sku: string; name: string } | null; quantity: string }[];
    }[];
    sales: {
        id: string;
        currency: string;
        total_amount: number;
        payment_method: string;
        sold_at: string | null;
        customer: { id: string; code: string; name: string } | null;
        invoice: { public_id: string; number: string } | null;
        payment: { public_id: string; number: string } | null;
        lines: { item: { sku: string; name: string } | null; quantity: string; total_amount: number }[];
    }[];
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
    stock: FieldStock;
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
    stock,
    currencies,
    defaultCurrency,
    storageKey,
    storageEncryptionKey,
}: Props) {
    const { props } = usePage<PageProps>();
    const t = useMemo(() => createTranslator(props.app.locale), [props.app.locale]);
    const fieldValue = (value: string) => t('field.value.' + value);
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
    const stockRequestForm = useForm({
        type: 'replenishment',
        inventory_item_id: '',
        location_id: '',
        central_id: '',
        quantity: '',
        note: '',
    });
    const stockCountForm = useForm({
        warehouse_id: '',
        lines: [] as { inventory_item_id: number; counted_quantity: string }[],
        note: '',
    });
    const [countedQuantities, setCountedQuantities] = useState<Record<number, string>>({});
    const saleForm = useForm({
        customer_id: '',
        warehouse_id: '',
        inventory_item_id: '',
        currency: defaultCurrency || 'USD',
        payment_method: 'cash',
        lines: [] as { inventory_item_id: string; quantity: string; unit_amount: number }[],
        note: '',
    });
    const [saleQuantity, setSaleQuantity] = useState('');
    const [saleUnitPrice, setSaleUnitPrice] = useState('');

    const submitStockRequest = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const replenishment = stockRequestForm.data.type === 'replenishment';
        stockRequestForm.transform((data) => ({
            type: data.type,
            inventory_item_id: data.inventory_item_id,
            source_warehouse_id: replenishment ? data.central_id : data.location_id,
            destination_warehouse_id: replenishment ? data.location_id : data.central_id,
            quantity: data.quantity,
            note: data.note,
        }));
        stockRequestForm.post('/field/inventory-requests', {
            preserveScroll: true,
            onSuccess: () => stockRequestForm.reset(),
            onFinish: () => stockRequestForm.transform((data) => data),
        });
    };

    const submitStockCount = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const location = stock.locations.find((candidate) => String(candidate.id) === stockCountForm.data.warehouse_id);
        if (!location) return;
        stockCountForm.transform((data) => ({
            warehouse_id: data.warehouse_id,
            lines: location.balances.map((balance) => ({
                inventory_item_id: balance.item_id,
                counted_quantity: countedQuantities[balance.item_id] ?? '',
            })),
            note: data.note,
        }));
        stockCountForm.post('/field/stock-counts', {
            preserveScroll: true,
            onSuccess: () => {
                stockCountForm.reset();
                setCountedQuantities({});
            },
            onFinish: () => stockCountForm.transform((data) => data),
        });
    };

    const saleUnitAmount = parseMoneyToMinor(saleUnitPrice, saleForm.data.currency);
    const saleTotal =
        saleUnitAmount === null || Number(saleQuantity) <= 0 ? null : Math.round(saleUnitAmount * Number(saleQuantity));

    const submitInventorySale = () => {
        if (
            saleUnitAmount === null ||
            saleUnitAmount < 1 ||
            !Number.isFinite(Number(saleQuantity)) ||
            Number(saleQuantity) <= 0
        ) {
            saleForm.setError('lines', t('field.error.positive_sale'));
            return;
        }
        saleForm.transform((data) => ({
            customer_id: data.customer_id,
            warehouse_id: data.warehouse_id,
            currency: data.currency,
            payment_method: data.payment_method,
            idempotency_key: newIdempotencyKey(),
            lines: [{ inventory_item_id: data.inventory_item_id, quantity: saleQuantity, unit_amount: saleUnitAmount }],
            note: data.note,
        }));
        saleForm.post('/field/inventory-sales', {
            preserveScroll: true,
            onSuccess: () => {
                saleForm.reset();
                setSaleQuantity('');
                setSaleUnitPrice('');
            },
            onFinish: () => saleForm.transform((data) => data),
        });
    };

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
            setError(t('field.error.offline'));
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
            if (!response.ok) throw new Error(t('field.error.refresh'));

            const changedCustomers = new Map(customers.map((customer) => [customer.id, customer]));
            body.data.customers.forEach((customer) => changedCustomers.set(customer.id, customer));
            (body.tombstones?.customers ?? []).forEach((customerId) => changedCustomers.delete(customerId));
            const nextCustomers = [...changedCustomers.values()];
            setCustomers(nextCustomers);
            await persist(pending, body.sync_token, nextCustomers);
            setMessage(t('field.message.refreshed'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : t('field.error.refresh'));
        } finally {
            setBusy(false);
        }
    }, [customers, online, pending, persist, syncToken, t]);

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
                    throw new Error(t('field.error.sync'));

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
                        ? t('field.message.synced')
                        : t('field.message.some_queued'),
                );
            } catch (caught) {
                setError(caught instanceof Error ? caught.message : t('field.error.sync'));
            } finally {
                setBusy(false);
            }
        },
        [online, pending, persist, t],
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
            setError(t('field.error.choose_customer'));
            return;
        }

        const amountMinor = parseMoneyToMinor(amount, currency);
        if (amountMinor === null) {
            setError(t('field.error.amount'));
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
                ? t('field.message.queued_online')
                : t('field.message.queued_offline'),
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
        setMessage(t('field.message.cleared'));
        setError(null);
    };

    const updateFieldDay = async (action: 'check-in' | 'check-out') => {
        if (!online) {
            setError(t('field.error.online'));
            return;
        }
        if (!('geolocation' in navigator)) {
            setError(t('field.error.geolocation'));
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
            if (!response.ok || !body.data) throw new Error(body.message ?? t('field.error.refresh'));

            setFieldDay(action === 'check-out' ? null : body.data);
            if (action === 'check-out') setCheckoutNote('');
            setMessage(body.message ?? (action === 'check-in' ? t('field.message.day_started') : t('field.message.day_ended')));
        } catch (caught) {
            if (typeof caught === 'object' && caught !== null && 'code' in caught) {
                const locationError = caught as GeolocationPositionError;
                setError(
                    locationError.code === locationError.PERMISSION_DENIED
                        ? t('field.error.location_permission')
                        : t('field.error.location_unreliable'),
                );
            } else {
                setError(caught instanceof Error ? caught.message : t('field.error.refresh'));
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
            setError(t('field.error.geolocation'));
            return;
        }
        setLocationBusy(true);
        setError(null);
        try {
            const position = await captureLocation();
            setRouteOrigin({ latitude: position.coords.latitude, longitude: position.coords.longitude });
            setNearbyOrder(true);
            setMessage(t('field.message.route_sorted'));
        } catch {
            setError(t('field.error.nearby_location'));
        } finally {
            setLocationBusy(false);
        }
    };

    const recordVisit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!fieldDay) {
            setError(t('field.error.start_day'));
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
            if (!response.ok || !body.data) throw new Error(body.message ?? t('field.error.visit'));

            setCollectorRoute(body.data);
            setSelectedStopId('');
            setVisitNote('');
            setMessage(body.message ?? t('field.message.visit_saved'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : t('field.error.visit'));
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
            if (!response.ok || !body.data) throw new Error(body.message ?? t('field.error.task'));
            if (body.data.status === 'completed' || body.data.status === 'cancelled') {
                const remaining = tasks.filter((item) => item.id !== body.data?.id);
                setTasks(remaining);
                setSelectedTaskId(remaining[0]?.id ?? '');
            } else {
                replaceTask(body.data);
            }
            setMessage(body.message ?? t('field.message.task_updated'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : t('field.error.task'));
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
            if (!response.ok || !body.data) throw new Error(body.message ?? t('field.error.message'));
            replaceTask(body.data);
            setTaskReply('');
            setTaskAttachment(null);
            setMessage(body.message ?? t('field.message.sent'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : t('field.error.message'));
        } finally {
            setTaskBusy(false);
        }
    };

    const submitCustodyRequest = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!online) {
            setError(t('field.error.custody_online'));
            return;
        }
        const amount = parseMoneyToMinor(custodyAmount, custodyCurrency);
        if (amount === null || amount <= 0) {
            setError(t('field.error.custody_amount'));
            return;
        }
        if (custodyDescription.trim() === '') {
            setError(t('field.error.custody_description'));
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
            <Head title={t('field.title')} />
            <div className="mx-auto max-w-4xl pb-24">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="eyebrow">{t('field.eyebrow')}</p>
                        <h1 className="page-title">{t('field.collector_desk')}</h1>
                        <p className="page-subtitle">{t('field.subtitle')}</p>
                    </div>
                    <div
                        className={`inline-flex items-center gap-2 self-start rounded-full px-3 py-2 text-xs font-semibold ${online ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
                    >
                        {online ? <Wifi size={15} /> : <CloudOff size={15} />}
                        {online ? t('field.online') : t('field.offline')}
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="card p-4">
                        <p className="eyebrow">{t('field.field_day')}</p>
                        <p className="mt-2 text-lg font-semibold">
                            {fieldDay ? t('field.checked_in') : t('field.not_started')}
                        </p>
                        <p className="mt-1 text-xs text-muted">
                            {fieldDay
                                ? t('field.location_recorded')
                                : t('field.start_route')}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">{t('field.shift')}</p>
                        <p className="mt-2 text-lg font-semibold">{shift ? t('field.open') : t('field.not_open')}</p>
                        <p className="mt-1 text-xs text-muted">
                            {shift
                                ? `${shift.payment_count} posted payment(s)`
                                : t('field.open_shift_before')}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">{t('field.today')}</p>
                        <p className="mt-2 text-lg font-semibold">
                            {summary.payment_count} {t('field.payments')}
                        </p>
                        <p className="mt-1 text-xs text-muted">
                            {entriesOrEmpty(summary.totals)
                                .map(([code, value]) => formatMoney(value, code))
                                .join(' · ') || 'Nothing posted yet'}
                        </p>
                    </div>
                    <div className="card p-4">
                        <p className="eyebrow">{t('field.queue')}</p>
                        <p className="mt-2 text-lg font-semibold">
                            {pending.length} {t('field.pending')}
                        </p>
                        <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 text-xs font-semibold text-brand disabled:opacity-50"
                                disabled={!online || busy || pending.length === 0}
                                onClick={() => void pushQueue()}
                            >
                                <RefreshCw size={13} className={busy ? 'animate-spin' : ''} /> {t('field.synchronize_now')}
                            </button>
                            <ConfirmDialog
                                title={t('field.clear_data_title')}
                                description={
                                    pending.length > 0
                                        ? t('field.clear_data_pending')
                                        : t('field.clear_data_empty')
                                }
                                confirmLabel={t('field.clear_device_data')}
                                destructive={pending.length > 0}
                                onConfirm={clearDeviceData}
                            >
                                <button
                                    type="button"
                                    className="text-xs font-semibold text-coral disabled:opacity-50"
                                    disabled={busy}
                                >
                                    {t('field.clear_device_data')}
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
                                <h2 className="text-balance text-xl font-semibold">{t('field.cash_custody')}</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {t('field.cash_custody_description')}
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
                                    <p className="eyebrow">{t('field.pending_review')}</p>
                                <p className="mt-1 font-semibold tabular-nums">{custody.position.pending_count}</p>
                            </div>
                        </div>
                    </div>
                    <form
                        className="grid gap-4 p-5 sm:grid-cols-2"
                        onSubmit={(event) => void submitCustodyRequest(event)}
                    >
                        <label className="field-label">
                            {t('field.request_type')}
                            <ResponsiveSelect
                                className="mt-1"
                                value={custodyType}
                                onChange={(event) => setCustodyType(event.target.value as 'expense' | 'handover')}
                            >
                                <option value="expense">{t('field.field_expense')}</option>
                                <option value="handover">{t('field.cash_handover')}</option>
                            </ResponsiveSelect>
                        </label>
                        <label className="field-label">
                            {t('field.currency')}
                            <CurrencyCombobox
                                className="field mt-1"
                                value={custodyCurrency}
                                currencies={currencyOptions}
                                onChange={setCustodyCurrency}
                            />
                        </label>
                        <label className="field-label">
                            {t('field.amount')}
                            <input
                                className="field mt-1 tabular-nums"
                                inputMode="decimal"
                                value={custodyAmount}
                                onChange={(event) => setCustodyAmount(event.target.value)}
                            />
                        </label>
                        <label className="field-label">
                            {t('field.reference_optional')}
                            <input
                                className="field mt-1"
                                value={custodyReference}
                                maxLength={120}
                                placeholder={t('field.receipt_reference')}
                                onChange={(event) => setCustodyReference(event.target.value)}
                            />
                        </label>
                        <label className="field-label sm:col-span-2">
                            {t('field.description')}
                            <textarea
                                className="field mt-1 min-h-20"
                                value={custodyDescription}
                                maxLength={2000}
                                placeholder={
                                    custodyType === 'expense'
                                        ? t('field.expense_prompt')
                                        : t('field.handover_prompt')
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
                                <p className="eyebrow">{t('field.recent_custody')}</p>
                            </div>
                            <div className="divide-y divide-line">
                                {custody.entries.slice(0, 6).map((entry) => (
                                    <div key={entry.id} className="flex items-start justify-between gap-4 px-5 py-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-semibold capitalize">{fieldValue(entry.type)}</p>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${entry.status === 'posted' ? 'bg-emerald-50 text-emerald-700' : entry.status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'}`}
                                                >
                                                    {fieldValue(entry.status)}
                                                </span>
                                            </div>
                                            <p className="mt-1 line-clamp-2 text-xs text-muted">{entry.description}</p>
                                            {entry.review_note && (
                                                <p className="mt-1 text-xs text-muted">
                                                    {t('field.manager')}: {entry.review_note}
                                                </p>
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

                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line p-5">
                        <h2 className="text-balance text-xl font-semibold">{t('field.field_stock')}</h2>
                        <p className="mt-1 text-pretty text-sm text-muted">
                            {t('field.field_stock_description')}
                        </p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {stock.locations.flatMap((location) =>
                                location.balances.map((balance) => (
                                    <div
                                        key={`${location.id}-${balance.item_id}`}
                                        className="rounded-xl border border-line px-4 py-3"
                                    >
                                        <p className="truncate text-sm font-semibold">{balance.name}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {location.code} · {balance.sku}
                                        </p>
                                        <p className="mt-2 font-semibold tabular-nums text-brand">{balance.quantity}</p>
                                    </div>
                                )),
                            )}
                            {stock.locations.every((location) => location.balances.length === 0) && (
                                <p className="text-sm text-muted">{t('field.no_material')}</p>
                            )}
                        </div>
                    </div>
                    {stock.locations.length > 0 && stock.central_locations.length > 0 && (
                        <form onSubmit={submitStockRequest} className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                            <label className="field-label">
                                {t('field.request_type')}
                                <ResponsiveSelect
                                    className="mt-1"
                                    value={stockRequestForm.data.type}
                                    onChange={(event) => stockRequestForm.setData('type', event.target.value)}
                                >
                                    <option value="replenishment">{t('field.replenishment')}</option>
                                    <option value="return">{t('field.return_unused_stock')}</option>
                                </ResponsiveSelect>
                            </label>
                            <label className="field-label">
                                {t('field.material')}
                                <ResponsiveSelect
                                    className="mt-1"
                                    value={stockRequestForm.data.inventory_item_id}
                                    onChange={(event) =>
                                        stockRequestForm.setData('inventory_item_id', event.target.value)
                                    }
                                >
                                    <option value="">{t('field.select_material')}</option>
                                    {stock.items.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.sku} · {item.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                            <label className="field-label">
                                {t('field.stock_location')}
                                <ResponsiveSelect
                                    className="mt-1"
                                    value={stockRequestForm.data.location_id}
                                    onChange={(event) => stockRequestForm.setData('location_id', event.target.value)}
                                >
                                    <option value="">{t('field.select_location')}</option>
                                    {stock.locations.map((location) => (
                                        <option key={location.id} value={location.id}>
                                            {location.code} · {location.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                            <label className="field-label">
                                {t('field.central_warehouse')}
                                <ResponsiveSelect
                                    className="mt-1"
                                    value={stockRequestForm.data.central_id}
                                    onChange={(event) => stockRequestForm.setData('central_id', event.target.value)}
                                >
                                    <option value="">{t('field.select_warehouse')}</option>
                                    {stock.central_locations.map((location) => (
                                        <option key={location.id} value={location.id}>
                                            {location.code} · {location.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                            <label className="field-label">
                                {t('field.quantity')}
                                <input
                                    className="field mt-1 tabular-nums"
                                    inputMode="decimal"
                                    value={stockRequestForm.data.quantity}
                                    onChange={(event) => stockRequestForm.setData('quantity', event.target.value)}
                                    placeholder="0.000"
                                />
                                {stockRequestForm.errors.quantity && (
                                    <span className="field-error">{stockRequestForm.errors.quantity}</span>
                                )}
                            </label>
                            <label className="field-label">
                                {t('field.note_optional')}
                                <input
                                    className="field mt-1"
                                    value={stockRequestForm.data.note}
                                    onChange={(event) => stockRequestForm.setData('note', event.target.value)}
                                    placeholder={t('field.route_context')}
                                />
                            </label>
                            <div className="flex justify-end sm:col-span-2 lg:col-span-3">
                                <button className="button-primary" disabled={stockRequestForm.processing}>
                                    {t('field.submit_stock_request')}
                                </button>
                            </div>
                        </form>
                    )}
                    {stock.locations.some((location) => location.balances.length > 0) && (
                        <div className="border-t border-line p-5">
                            <h3 className="text-balance font-semibold">{t('field.sell_stock')}</h3>
                            <p className="mt-1 text-pretty text-xs text-muted">
                                {t('field.sell_stock_description')}
                            </p>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label className="field-label">
                                    {t('field.customer')}
                                    <CustomerCombobox
                                        className="mt-1"
                                        aria-label={t('field.sale_customer')}
                                        value={saleForm.data.customer_id}
                                        customers={customers.map((customer) => ({
                                            id: customer.id,
                                            code: customer.code,
                                            name: `${customer.first_name} ${customer.last_name ?? ''}`.trim(),
                                            phone: customer.phone,
                                            status: 'active',
                                            balance_amount: customer.balance_amount,
                                            balance_currency: customer.balance_currency,
                                        }))}
                                        onChange={(value) => saleForm.setData('customer_id', value)}
                                    />
                                </label>
                                <label className="field-label">
                                    {t('field.stock_location')}
                                    <ResponsiveSelect
                                        className="mt-1"
                                        value={saleForm.data.warehouse_id}
                                        onChange={(event) => saleForm.setData('warehouse_id', event.target.value)}
                                    >
                                        <option value="">{t('field.select_location')}</option>
                                        {stock.locations
                                            .filter((location) => location.balances.length > 0)
                                            .map((location) => (
                                                <option key={location.id} value={location.id}>
                                                    {location.code} · {location.name}
                                                </option>
                                            ))}
                                    </ResponsiveSelect>
                                </label>
                                <label className="field-label">
                                    {t('field.item')}
                                    <ResponsiveSelect
                                        className="mt-1"
                                        value={saleForm.data.inventory_item_id}
                                        onChange={(event) => saleForm.setData('inventory_item_id', event.target.value)}
                                    >
                                        <option value="">{t('field.select_item')}</option>
                                        {stock.locations
                                            .find((location) => String(location.id) === saleForm.data.warehouse_id)
                                            ?.balances.map((balance) => (
                                                <option key={balance.item_id} value={balance.item_id}>
                                                    {balance.sku} · {balance.name} · {balance.quantity} available
                                                </option>
                                            ))}
                                    </ResponsiveSelect>
                                </label>
                                <label className="field-label">
                                    {t('field.quantity')}
                                    <input
                                        className="field mt-1 tabular-nums"
                                        inputMode="decimal"
                                        value={saleQuantity}
                                        onChange={(event) => setSaleQuantity(event.target.value)}
                                        placeholder="0.000"
                                    />
                                </label>
                                <label className="field-label">
                                    {t('field.unit_price')}
                                    <input
                                        className="field mt-1 tabular-nums"
                                        inputMode="decimal"
                                        value={saleUnitPrice}
                                        onChange={(event) => setSaleUnitPrice(event.target.value)}
                                        placeholder="0.00"
                                    />
                                </label>
                                <label className="field-label">
                                    {t('field.currency')}
                                    <CurrencyCombobox
                                        className="field mt-1"
                                        value={saleForm.data.currency}
                                        currencies={currencyOptions}
                                        onChange={(value) => saleForm.setData('currency', value)}
                                    />
                                </label>
                                <label className="field-label">
                                    {t('field.payment_method')}
                                    <ResponsiveSelect
                                        className="mt-1"
                                        value={saleForm.data.payment_method}
                                        onChange={(event) => saleForm.setData('payment_method', event.target.value)}
                                    >
                                        <option value="cash">{t('field.cash')}</option>
                                        <option value="mobile_wallet">{t('field.mobile_wallet')}</option>
                                        <option value="card">{t('field.card')}</option>
                                        <option value="bank_transfer">{t('field.bank_transfer')}</option>
                                    </ResponsiveSelect>
                                </label>
                                <label className="field-label sm:col-span-2">
                                    {t('field.note_optional')}
                                    <input
                                        className="field mt-1"
                                        value={saleForm.data.note}
                                        onChange={(event) => saleForm.setData('note', event.target.value)}
                                        placeholder={t('field.item_handover')}
                                    />
                                </label>
                            </div>
                            {saleForm.errors.lines && <p className="field-error mt-3">{saleForm.errors.lines}</p>}
                            <div className="mt-4 flex items-center justify-between gap-4">
                                <p className="text-sm text-muted">
                                    {t('field.total')}:{' '}
                                    <span className="font-semibold tabular-nums text-ink">
                                        {saleTotal === null ? '—' : formatMoney(saleTotal, saleForm.data.currency)}
                                    </span>
                                </p>
                                <ConfirmDialog
                                    title={t('field.record_sale_title')}
                                    description={`This creates a paid invoice for ${saleTotal === null ? 'the calculated total' : formatMoney(saleTotal, saleForm.data.currency)} and immediately removes the item from your stock.`}
                                    confirmLabel={t('field.record_sale')}
                                    onConfirm={submitInventorySale}
                                >
                                    <button
                                        type="button"
                                        className="button-primary"
                                        disabled={
                                            saleForm.processing ||
                                            saleTotal === null ||
                                            !saleForm.data.customer_id ||
                                            !saleForm.data.inventory_item_id
                                        }
                                    >
                                        Record sale
                                    </button>
                                </ConfirmDialog>
                            </div>
                            {stock.sales.length > 0 && (
                                <div className="mt-5 divide-y divide-line border-t border-line">
                                    {stock.sales.slice(0, 5).map((sale) => (
                                        <div key={sale.id} className="flex items-start justify-between gap-4 py-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold">
                                                    {sale.customer?.name ?? 'Customer sale'}
                                                </p>
                                                <p className="mt-1 truncate text-xs text-muted">
                                                    {sale.invoice?.number} ·{' '}
                                                    {sale.lines
                                                        .map((line) => `${line.quantity} ${line.item?.name ?? ''}`)
                                                        .join(', ')}
                                                </p>
                                            </div>
                                            <p className="shrink-0 font-semibold tabular-nums">
                                                {formatMoney(sale.total_amount, sale.currency)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                    {stock.locations.some((location) => location.balances.length > 0) && (
                        <form onSubmit={submitStockCount} className="border-t border-line p-5">
                            <h3 className="text-balance font-semibold">{t('field.submit_physical_count')}</h3>
                            <p className="mt-1 text-pretty text-xs text-muted">
                                {t('field.physical_count_description')}
                            </p>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <label className="field-label">
                                    {t('field.stock_location')}
                                    <ResponsiveSelect
                                        className="mt-1"
                                        value={stockCountForm.data.warehouse_id}
                                        onChange={(event) => {
                                            stockCountForm.setData('warehouse_id', event.target.value);
                                            setCountedQuantities({});
                                        }}
                                    >
                                        <option value="">{t('field.select_location')}</option>
                                        {stock.locations
                                            .filter((location) => location.balances.length > 0)
                                            .map((location) => (
                                                <option key={location.id} value={location.id}>
                                                    {location.code} · {location.name}
                                                </option>
                                            ))}
                                    </ResponsiveSelect>
                                </label>
                                <label className="field-label">
                                    {t('field.count_note')}
                                    <input
                                        className="field mt-1"
                                        value={stockCountForm.data.note}
                                        onChange={(event) => stockCountForm.setData('note', event.target.value)}
                                        placeholder={t('field.end_route')}
                                    />
                                </label>
                            </div>
                            {stock.locations.find(
                                (location) => String(location.id) === stockCountForm.data.warehouse_id,
                            ) && (
                                <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {stock.locations
                                        .find((location) => String(location.id) === stockCountForm.data.warehouse_id)
                                        ?.balances.map((balance) => (
                                            <label
                                                key={balance.item_id}
                                                className="rounded-xl border border-line p-3 text-sm font-semibold"
                                            >
                                                <span className="block truncate">{balance.name}</span>
                                                <span className="mt-1 block text-xs font-normal text-muted">
                                                    {t('field.system')}: <span className="tabular-nums">{balance.quantity}</span>
                                                </span>
                                                <input
                                                    className="field mt-2 tabular-nums"
                                                    inputMode="decimal"
                                                    value={countedQuantities[balance.item_id] ?? ''}
                                                    onChange={(event) =>
                                                        setCountedQuantities((current) => ({
                                                            ...current,
                                                            [balance.item_id]: event.target.value,
                                                        }))
                                                    }
                                                    placeholder={t('field.physical_quantity')}
                                                />
                                            </label>
                                        ))}
                                </div>
                            )}
                            {stockCountForm.errors.lines && (
                                <p className="field-error mt-3">{stockCountForm.errors.lines}</p>
                            )}
                            <div className="mt-4 flex justify-end">
                                <ConfirmDialog
                                    title={t('field.submit_count_title')}
                                    description={t('field.submit_count_description')}
                                    confirmLabel={t('field.submit_count')}
                                    onConfirm={() => document.getElementById('field-stock-count-submit')?.click()}
                                >
                                    <button
                                        type="button"
                                        className="button-secondary"
                                        disabled={!stockCountForm.data.warehouse_id || stockCountForm.processing}
                                    >
                                        {t('field.review_submit')}
                                    </button>
                                </ConfirmDialog>
                                <button id="field-stock-count-submit" type="submit" className="hidden">
                                    Submit
                                </button>
                            </div>
                        </form>
                    )}
                    {stock.requests.length > 0 && (
                        <div className="divide-y divide-line border-t border-line">
                            {stock.requests.slice(0, 6).map((request) => (
                                <div key={request.id} className="flex items-start justify-between gap-4 px-5 py-4">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">
                                            {request.item?.name ?? 'Stock request'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {request.source?.code} → {request.destination?.code}
                                            {request.review_note ? ` · ${request.review_note}` : ''}
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-end">
                                        <p className="text-sm font-semibold tabular-nums">{request.quantity}</p>
                                        <p className="mt-1 text-xs font-semibold capitalize text-muted">
                                            {fieldValue(request.status)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <MapPin className="mt-0.5 shrink-0 text-brand" size={19} />
                        <div>
                            <h2 className="text-sm font-semibold">{t('field.field_attendance')}</h2>
                            <p className="mt-1 text-pretty text-xs text-muted">
                                {t('field.attendance_description')}
                            </p>
                        </div>
                    </div>
                    {fieldDay && (
                        <label className="field-label min-w-0 flex-1 sm:max-w-sm">
                            {t('field.checkout_note')}
                            <textarea
                                className="field mt-1 min-h-16"
                                value={checkoutNote}
                                maxLength={2000}
                                placeholder={t('field.checkout_placeholder')}
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
                                <h2 className="text-balance text-xl font-semibold">{t('field.assigned_tasks')}</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {t('field.assigned_tasks_description')}
                                </p>
                            </div>
                        </div>
                        <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-brand tabular-nums">
                            {tasks.length} {t('field.open_tasks')}
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
                                                    aria-label={t('field.unread_messages')}
                                                />
                                            )}
                                        </div>
                                        <p className="mt-2 text-xs capitalize text-muted">
                                            {fieldValue(task.priority)} · {fieldValue(task.status)}
                                        </p>
                                    </button>
                                ))}
                            </div>
                            {selectedTask && (
                                <div className="min-w-0 p-5">
                                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                        <div>
                                            <p className="eyebrow">{fieldValue(selectedTask.priority)} priority</p>
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
                                                ? t('field.acknowledge')
                                                : selectedTask.status === 'acknowledged'
                                                  ? t('field.start_task')
                                                  : selectedTask.status === 'in_progress'
                                                    ? t('field.complete_task')
                                                    : t('field.completed')}
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
                                                    t('field.open_customer_details')}
                                            </p>
                                        </Link>
                                    )}
                                    <div className="mt-5 border-t border-line pt-5">
                                        <div className="flex items-center gap-2">
                                            <MessageSquare size={17} className="text-brand" />
                                            <h4 className="text-sm font-semibold">{t('field.conversation')}</h4>
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
                                                    {t('field.no_messages')}
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
                                                {t('field.reply')}
                                                <textarea
                                                    className="field mt-1 min-h-20"
                                                    value={taskReply}
                                                    maxLength={5000}
                                                    onChange={(event) => setTaskReply(event.target.value)}
                                                />
                                            </label>
                                            <label className="field-label mt-3 block">
                                                {t('field.attachment_optional')}
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
                                                    {t('field.send_reply')}
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
                            <p className="mt-3 font-semibold">{t('field.no_open_tasks')}</p>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                {t('field.new_assignments')}
                            </p>
                        </div>
                    )}
                </section>

                <section className="card mt-6 overflow-hidden">
                    <div className="flex flex-col gap-4 border-b border-line p-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="eyebrow">{t('field.todays_route')}</p>
                            <h2 className="mt-1 text-xl font-semibold text-balance">
                                {collectorRoute
                                    ? `${collectorRoute.completed_count}/${collectorRoute.stop_count} stops completed`
                                    : t('field.no_route')}
                            </h2>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                {t('field.route_description')}
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
                                    <LocateFixed size={15} /> {t('field.nearest_first')}
                                </button>
                                <button
                                    type="button"
                                    className={!nearbyOrder ? 'button-secondary' : 'button-quiet'}
                                    onClick={() => setNearbyOrder(false)}
                                >
                                    <ListOrdered size={15} /> {t('field.planned_order')}
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
                                                            {fieldValue(stop.outcome)}
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
                                                    {t('field.collect')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="button-primary"
                                                    onClick={() => setSelectedStopId(selected ? '' : stop.id)}
                                                    disabled={!fieldDay || stop.outcome !== 'pending'}
                                                >
                                                    {t('field.outcome')}
                                                </button>
                                            </div>
                                        </div>
                                        {selected && (
                                            <form
                                                onSubmit={recordVisit}
                                                className="mt-4 grid gap-4 rounded-xl border border-line bg-sand/50 p-4 sm:grid-cols-[0.7fr_1.3fr_auto] sm:items-end"
                                            >
                                                <label>
                                                    <span className="field-label">{t('field.visit_outcome')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={visitOutcome}
                                                        onChange={(event) =>
                                                            setVisitOutcome(
                                                                event.target.value as FieldRouteStop['outcome'],
                                                            )
                                                        }
                                                    >
                                                        <option value="collected">{t('field.collected')}</option>
                                                        <option value="no_answer">{t('field.no_answer')}</option>
                                                        <option value="refused">{t('field.refused')}</option>
                                                        <option value="reschedule">{t('field.reschedule')}</option>
                                                        <option value="address_issue">{t('field.address_issue')}</option>
                                                    </ResponsiveSelect>
                                                </label>
                                                <label>
                                                    <span className="field-label">{t('field.visit_note')}</span>
                                                    <input
                                                        className="field"
                                                        value={visitNote}
                                                        onChange={(event) => setVisitNote(event.target.value)}
                                                        placeholder={t('field.operational_note')}
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
                            <p className="mt-3 font-semibold">{t('field.no_stops')}</p>
                            <p className="mt-1 text-sm text-muted">{t('field.route_publish')}</p>
                        </div>
                    )}
                </section>

                {!shift && (
                    <div className="mt-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>{t('field.open_shift_before')}</span>
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
                        <p className="eyebrow">{t('field.customer_queue')}</p>
                        <div className="flex items-start justify-between gap-4">
                            <h2 className="mt-1 text-xl font-semibold">{t('field.choose_customer')}</h2>
                            <button
                                type="button"
                                className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-brand disabled:opacity-50"
                                onClick={() => void refreshSnapshot()}
                                disabled={!online || busy}
                            >
                                <RefreshCw size={13} className={busy ? 'animate-spin' : ''} /> {t('field.refresh_list')}
                            </button>
                        </div>
                        <div className="relative mt-4">
                            <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                            <input
                                className="field ps-10"
                                aria-label={t('field.search_customers')}
                                placeholder={t('field.search_customers')}
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
                            <p className="p-6 text-sm text-muted">{t('field.no_customer_match')}</p>
                        )}
                    </div>
                </section>

                <form className="card mt-6 space-y-5 p-5" onSubmit={(event) => void queuePayment(event)}>
                    <div className="flex items-center gap-2">
                        <CreditCard size={18} className="text-brand" />
                        <div>
                            <p className="font-semibold">{t('field.record_collection')}</p>
                            <p className="text-xs text-muted">
                                {selectedCustomer
                                    ? `${selectedCustomer.first_name} ${selectedCustomer.last_name ?? ''}`
                                    : t('field.select_customer')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className="block">
                            <span className="field-label">
                                {t('field.amount')} ({currency})
                            </span>
                            <input
                                className="field"
                                aria-label={t('field.field_payment_amount')}
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
                            <span className="field-label">{t('field.currency')}</span>
                            <CurrencyCombobox
                                aria-label={t('field.field_payment_currency')}
                                value={currency}
                                currencies={currencyOptions}
                                onChange={setCurrency}
                            />
                        </label>
                        <label className="block sm:col-span-2">
                            <span className="field-label">{t('field.payment_method')}</span>
                            <ResponsiveSelect
                                aria-label={t('field.field_payment_method')}
                                value={method}
                                onChange={(event) => setMethod(event.target.value)}
                            >
                                <option value="cash">{t('field.cash')}</option>
                                <option value="bank_transfer">{t('field.bank_transfer')}</option>
                                <option value="card">{t('field.card')}</option>
                                <option value="mobile_wallet">{t('field.mobile_wallet')}</option>
                            </ResponsiveSelect>
                        </label>
                    </div>
                    <button
                        type="submit"
                        className="button-primary w-full justify-center"
                        disabled={!shift || !selectedCustomer || busy}
                    >
                        <CheckCircle2 size={17} /> {t('field.save_payment')}
                    </button>
                    <p className="text-xs text-muted">
                        {t('field.encryption_note')}
                    </p>
                </form>

                {pending.length > 0 && (
                    <section className="card mt-6 p-5">
                        <h2 className="font-semibold">{t('field.pending_review')}</h2>
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

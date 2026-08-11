export type QueuedFieldPayment = {
    customer_id: string;
    amount: number;
    currency: string;
    method: string;
    idempotency_key: string;
    reference?: string;
    last_error?: string;
};

export type FieldCustomerCache = {
    id: string;
    code: string;
    first_name: string;
    last_name: string | null;
    phone: string;
    balance_amount: number;
    balance_currency: string;
    zone: { code: string; name: string } | null;
};

export type FieldCurrencyCache = {
    code: string;
    name: string;
    decimal_digits: number;
};

export type CachedFieldSnapshot = {
    sync_token: string;
    generated_at: string;
    customers: FieldCustomerCache[];
    currencies: FieldCurrencyCache[];
    default_currency: string;
};

export type StoredFieldState = {
    key: string;
    sync_token: string | null;
    pending: QueuedFieldPayment[];
    cached_snapshot?: CachedFieldSnapshot;
};

const DATABASE_NAME = 'isp-manager-field';
const STORE_NAME = 'state';
const FALLBACK_PREFIX = 'isp-manager-field:';

function fallbackKey(key: string): string {
    return `${FALLBACK_PREFIX}${key}`;
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise<IDBDatabase>((resolve, reject) => {
        const request = window.indexedDB.open(DATABASE_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE_NAME, { keyPath: 'key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be opened.'));
    });
}

async function readIndexedState(key: string): Promise<StoredFieldState | null> {
    const database = await openDatabase();

    return new Promise<StoredFieldState | null>((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readonly').objectStore(STORE_NAME).get(key);
        request.onsuccess = () => resolve((request.result as StoredFieldState | undefined) ?? null);
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be read.'));
    }).finally(() => database.close());
}

async function writeIndexedState(state: StoredFieldState): Promise<void> {
    const database = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).put(state);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be saved.'));
    }).finally(() => database.close());
}

function readFallbackState(key: string): StoredFieldState | null {
    try {
        const raw = window.localStorage.getItem(fallbackKey(key));
        return raw === null ? null : (JSON.parse(raw) as StoredFieldState);
    } catch {
        return null;
    }
}

function writeFallbackState(state: StoredFieldState): void {
    try {
        window.localStorage.setItem(fallbackKey(state.key), JSON.stringify(state));
    } catch {
        // A private browsing context may deny local persistence; the in-memory queue still works.
    }
}

export async function readFieldState(key: string): Promise<StoredFieldState | null> {
    if (typeof window === 'undefined' || !('indexedDB' in window)) return readFallbackState(key);

    try {
        return await readIndexedState(key);
    } catch {
        return readFallbackState(key);
    }
}

export async function writeFieldState(state: StoredFieldState): Promise<void> {
    if (typeof window === 'undefined' || !('indexedDB' in window)) {
        writeFallbackState(state);
        return;
    }

    try {
        await writeIndexedState(state);
    } catch {
        writeFallbackState(state);
    }
}

export function emptyFieldState(key: string): StoredFieldState {
    return { key, sync_token: null, pending: [] };
}

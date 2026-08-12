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
const STORAGE_VERSION = 2;

type StoredFieldEnvelope = {
    key: string;
    version: typeof STORAGE_VERSION;
    iv: string;
    ciphertext: string;
};

type StoredFieldRecord = StoredFieldEnvelope | StoredFieldState;

function fallbackKey(key: string): string {
    return `${FALLBACK_PREFIX}${key}`;
}

function webCrypto(): Crypto {
    if (typeof window === 'undefined' || !window.crypto?.subtle) {
        throw new Error('Encrypted field storage is unavailable in this browser.');
    }

    return window.crypto;
}

function toBase64(bytes: Uint8Array): string {
    let binary = '';

    for (const byte of bytes) binary += String.fromCharCode(byte);

    return btoa(binary);
}

function fromBase64(value: string): Uint8Array {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index);

    return bytes;
}

function asArrayBuffer(bytes: Uint8Array): ArrayBuffer {
    return new Uint8Array(bytes).buffer as ArrayBuffer;
}

async function encryptionKey(material: string): Promise<CryptoKey> {
    const raw = fromBase64(material);

    if (raw.byteLength !== 32) throw new Error('Encrypted field storage key is invalid.');

    return webCrypto().subtle.importKey('raw', asArrayBuffer(raw), { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

function isEncryptedRecord(record: StoredFieldRecord): record is StoredFieldEnvelope {
    return (
        typeof record === 'object' &&
        record !== null &&
        'version' in record &&
        record.version === STORAGE_VERSION &&
        typeof record.iv === 'string' &&
        typeof record.ciphertext === 'string'
    );
}

async function encryptState(state: StoredFieldState, material: string): Promise<StoredFieldEnvelope> {
    const crypto = webCrypto();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: asArrayBuffer(iv) },
        await encryptionKey(material),
        new TextEncoder().encode(JSON.stringify(state)),
    );

    return {
        key: state.key,
        version: STORAGE_VERSION,
        iv: toBase64(iv),
        ciphertext: toBase64(new Uint8Array(ciphertext)),
    };
}

async function decryptState(record: StoredFieldEnvelope, material: string): Promise<StoredFieldState> {
    const plaintext = await webCrypto().subtle.decrypt(
        { name: 'AES-GCM', iv: asArrayBuffer(fromBase64(record.iv)) },
        await encryptionKey(material),
        asArrayBuffer(fromBase64(record.ciphertext)),
    );
    const state = JSON.parse(new TextDecoder().decode(plaintext)) as StoredFieldState;

    if (state.key !== record.key || !Array.isArray(state.pending))
        throw new Error('Encrypted field storage is invalid.');

    return state;
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise<IDBDatabase>((resolve, reject) => {
        const request = window.indexedDB.open(DATABASE_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE_NAME, { keyPath: 'key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be opened.'));
    });
}

async function readIndexedState(key: string, material: string): Promise<StoredFieldState | null> {
    const database = await openDatabase();

    const record = await new Promise<StoredFieldRecord | null>((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readonly').objectStore(STORE_NAME).get(key);
        request.onsuccess = () => resolve((request.result as StoredFieldRecord | undefined) ?? null);
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be read.'));
    }).finally(() => database.close());

    if (record === null) return null;
    if (isEncryptedRecord(record)) return decryptState(record, material);

    try {
        await writeIndexedState(record, material);
        return record;
    } catch {
        await deleteIndexedState(key);
        return null;
    }
}

async function writeIndexedState(state: StoredFieldState, material: string): Promise<void> {
    const encrypted = await encryptState(state, material);
    const database = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).put(encrypted);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be saved.'));
    }).finally(() => database.close());
}

async function deleteIndexedState(key: string): Promise<void> {
    const database = await openDatabase();

    await new Promise<void>((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).delete(key);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error ?? new Error('Field storage could not be cleared.'));
    }).finally(() => database.close());
}

async function readFallbackState(key: string, material: string): Promise<StoredFieldState | null> {
    try {
        const raw = window.localStorage.getItem(fallbackKey(key));
        if (raw === null) return null;

        const record = JSON.parse(raw) as StoredFieldRecord;
        if (isEncryptedRecord(record)) return decryptState(record, material);

        await writeFallbackState(record, material);
        return record;
    } catch {
        try {
            window.localStorage.removeItem(fallbackKey(key));
        } catch {
            // A private browsing context may deny local persistence.
        }
        return null;
    }
}

async function writeFallbackState(state: StoredFieldState, material: string): Promise<void> {
    try {
        window.localStorage.setItem(fallbackKey(state.key), JSON.stringify(await encryptState(state, material)));
    } catch {
        try {
            window.localStorage.removeItem(fallbackKey(state.key));
        } catch {
            // A private browsing context may deny local persistence.
        }
    }
}

export async function readFieldState(key: string, material: string): Promise<StoredFieldState | null> {
    if (typeof window === 'undefined' || !('indexedDB' in window)) return readFallbackState(key, material);

    try {
        return await readIndexedState(key, material);
    } catch {
        return readFallbackState(key, material);
    }
}

export async function writeFieldState(state: StoredFieldState, material: string): Promise<void> {
    if (typeof window === 'undefined' || !('indexedDB' in window)) {
        await writeFallbackState(state, material);
        return;
    }

    try {
        await writeIndexedState(state, material);
    } catch {
        await writeFallbackState(state, material);
    }
}

export async function clearFieldState(key: string): Promise<void> {
    try {
        if (typeof window !== 'undefined' && 'indexedDB' in window) await deleteIndexedState(key);
    } catch {
        // Fall through to the local-storage cleanup when IndexedDB is unavailable.
    }

    try {
        window.localStorage.removeItem(fallbackKey(key));
    } catch {
        // A private browsing context may deny local persistence.
    }
}

export function emptyFieldState(key: string): StoredFieldState {
    return { key, sync_token: null, pending: [] };
}

function formatUuid(bytes: Uint8Array): string {
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function createIdempotencyKey(prefix = 'request'): string {
    const browserCrypto = globalThis.crypto;

    if (typeof browserCrypto?.randomUUID === 'function') {
        return browserCrypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    if (typeof browserCrypto?.getRandomValues === 'function') {
        browserCrypto.getRandomValues(bytes);
    } else {
        const prefixSeed = Array.from(prefix).reduce((sum, character) => sum + character.charCodeAt(0), 0);

        for (let index = 0; index < bytes.length; index += 1) {
            bytes[index] = (Math.floor(Math.random() * 256) + prefixSeed + index) % 256;
        }
    }

    return formatUuid(bytes);
}

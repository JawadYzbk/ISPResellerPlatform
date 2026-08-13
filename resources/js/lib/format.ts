export function formatMoney(amountMinor: number, currency: string, locale = browserLocale()): string {
    const normalizedCurrency = currency.trim().toUpperCase();
    const fractionDigits = currencyFractionDigits(normalizedCurrency, locale);

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: normalizedCurrency,
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(amountMinor / 10 ** fractionDigits);
}

export function currencyFractionDigits(currency: string, locale = 'en-US'): number {
    const normalizedCurrency = currency.trim().toUpperCase();

    try {
        return (
            new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: normalizedCurrency,
            }).resolvedOptions().maximumFractionDigits ?? 2
        );
    } catch {
        return normalizedCurrency === 'JPY' || normalizedCurrency === 'LBP' ? 0 : 2;
    }
}

export function parseMoneyToMinor(value: string, currency: string): number | null {
    const normalized = value.trim();
    const match = /^(\d+)(?:\.(\d+))?$/.exec(normalized);
    if (!match) return null;

    const fractionDigits = currencyFractionDigits(currency);
    const fractionalPart = match[2] ?? '';
    const discardedDigits = fractionalPart.slice(fractionDigits);
    if (discardedDigits !== '' && /[1-9]/.test(discardedDigits)) return null;

    const scale = 10n ** BigInt(fractionDigits);
    const major = BigInt(match[1]);
    const minorFraction = BigInt(fractionalPart.slice(0, fractionDigits).padEnd(fractionDigits, '0') || '0');
    const amount = major * scale + minorFraction;
    const safeMaximum = BigInt(Number.MAX_SAFE_INTEGER);

    return amount > 0n && amount <= safeMaximum ? Number(amount) : null;
}

type Translate = (key: string) => string;

export function formatDuration(startedAt: string | null, endedAt: string | null, translate: Translate = (key) => key): string {
    if (!startedAt || !endedAt) return translate('Uptime unavailable');

    const minutes = Math.max(0, Math.floor((new Date(endedAt).getTime() - new Date(startedAt).getTime()) / 60000));
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const remainingMinutes = minutes % 60;

    if (days > 0) return translate(`${days}d ${hours}h`);
    if (hours > 0) return translate(`${hours}h ${remainingMinutes}m`);

    return translate(`${remainingMinutes}m`);
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unit]}`;
}

export function formatExpiryCountdown(value: string | null, translate: Translate = (key) => key): string {
    if (!value) return translate('No expiry date');
    const days = Math.ceil((new Date(value).getTime() - Date.now()) / 86_400_000);
    if (days < 0) {
        const count = Math.abs(days);
        return translate(`Expired ${count} ${count === 1 ? 'day' : 'days'} ago`);
    }
    if (days === 0) return translate('Expires today');
    if (days === 1) return translate('Expires tomorrow');

    return translate(`Expires in ${days} days`);
}

export function entriesOrEmpty<T>(value: Record<string, T> | null | undefined): [string, T][] {
    return Object.entries(value ?? {});
}

export function keysOrEmpty(value: object | null | undefined): string[] {
    return Object.keys(value ?? {});
}

export function formatDate(value: string | null, locale = browserLocale()): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(value));
}

function browserLocale(): string {
    if (typeof document === 'undefined') return 'en-US';

    return document.documentElement.lang.replace('_', '-') || 'en-US';
}

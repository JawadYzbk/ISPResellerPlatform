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
    if (!/^\d+(?:\.\d+)?$/.test(normalized)) return null;

    const amount = Number(normalized) * 10 ** currencyFractionDigits(currency);
    return Number.isSafeInteger(Math.round(amount)) && amount > 0 ? Math.round(amount) : null;
}

export function formatDuration(startedAt: string | null, endedAt: string | null): string {
    if (!startedAt || !endedAt) return 'Uptime unavailable';

    const minutes = Math.max(0, Math.floor((new Date(endedAt).getTime() - new Date(startedAt).getTime()) / 60000));
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const remainingMinutes = minutes % 60;

    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${remainingMinutes}m`;

    return `${remainingMinutes}m`;
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

export function formatExpiryCountdown(value: string | null): string {
    if (!value) return 'No expiry date';
    const days = Math.ceil((new Date(value).getTime() - Date.now()) / 86_400_000);
    if (days < 0) return `Expired ${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} ago`;
    if (days === 0) return 'Expires today';
    if (days === 1) return 'Expires tomorrow';

    return `Expires in ${days} days`;
}

export function formatDate(value: string | null, locale = browserLocale()): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(value));
}

function browserLocale(): string {
    if (typeof document === 'undefined') return 'en-US';

    return document.documentElement.lang.replace('_', '-') || 'en-US';
}

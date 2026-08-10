export function formatMoney(amountMinor: number, currency: string, locale = 'en-US'): string {
    const fractionDigits = currencyFractionDigits(currency);

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(amountMinor / 10 ** fractionDigits);
}

export function currencyFractionDigits(currency: string): number {
    return currency === 'JPY' ? 0 : currency === 'KWD' ? 3 : 2;
}

export function parseMoneyToMinor(value: string, currency: string): number | null {
    const normalized = value.trim();
    if (!/^\d+(?:\.\d+)?$/.test(normalized)) return null;

    const amount = Number(normalized) * 10 ** currencyFractionDigits(currency);
    return Number.isSafeInteger(Math.round(amount)) && amount > 0 ? Math.round(amount) : null;
}

export function formatDate(value: string | null, locale = 'en-US'): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(value));
}

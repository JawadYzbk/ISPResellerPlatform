export function formatMoney(amountMinor: number, currency: string, locale = 'en-US'): string {
    const fractionDigits = currency === 'JPY' ? 0 : currency === 'KWD' ? 3 : 2;

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(amountMinor / 10 ** fractionDigits);
}

export function formatDate(value: string | null, locale = 'en-US'): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(value));
}

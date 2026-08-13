<!doctype html>
<html lang="{{ $settings->locale }}" dir="{{ $settings->rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->number }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #182522; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 24px; letter-spacing: .04em; }
        h2 { color: #1b6b63; font-size: 12px; margin-bottom: 5px; text-transform: uppercase; }
        .header, .meta, .amount { width: 100%; }
        .header td, .meta td, .amount td { vertical-align: top; }
        .header { border-bottom: 2px solid #1b6b63; margin-bottom: 22px; padding-bottom: 12px; }
        .header-right { text-align: right; }
        .muted { color: #70807c; }
        .meta { border-collapse: collapse; margin-bottom: 22px; }
        .meta td { border-bottom: 1px solid #e4e9e5; padding: 10px 0; width: 50%; }
        .amount { background: #e5f1ed; margin: 20px 0; padding: 16px; }
        .amount .value { color: #1b6b63; font-size: 24px; font-weight: bold; text-align: right; }
        table.allocations { border-collapse: collapse; width: 100%; }
        .allocations th { color: #1b6b63; font-size: 10px; padding: 8px; text-align: left; text-transform: uppercase; }
        .allocations td { border-bottom: 1px solid #e4e9e5; padding: 8px; }
        .number { text-align: right; }
        .footer { border-top: 1px solid #e4e9e5; color: #70807c; font-size: 9px; margin-top: 35px; padding-top: 8px; }
        [dir="rtl"] .header-right, [dir="rtl"] .number, [dir="rtl"] .amount .value { text-align: left; }
        [dir="rtl"] .allocations th { text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><h1>{{ $tenant->name }}</h1><p class="muted">{{ $tenant->slug }}</p></td>
            <td class="header-right"><h1>{{ $formatter::label('Receipt', $settings->locale) }}</h1><p>{{ $payment->number }}</p><p class="muted">{{ $formatter::label($payment->status->value, $settings->locale) }}</p></td>
        </tr>
    </table>

    <table class="meta">
        <tr><td><h2>{{ $formatter::label('Received from', $settings->locale) }}</h2><p>{{ $payment->customer->full_name }}</p><p class="muted">{{ $payment->customer->code }}</p></td><td class="header-right"><h2>{{ $formatter::label('Received at', $settings->locale) }}</h2><p>{{ $formatter::date($payment->received_at, $settings->timezone) }}</p></td></tr>
        <tr><td><h2>{{ $formatter::label('Method', $settings->locale) }}</h2><p>{{ $formatter::label($payment->method, $settings->locale) }}</p></td><td class="header-right"><h2>{{ $formatter::label('Collector', $settings->locale) }}</h2><p>{{ $payment->actor?->name ?? '—' }}</p></td></tr>
    </table>

    <table class="amount"><tr><td><h2>{{ $formatter::label('Amount received', $settings->locale) }}</h2><p class="muted">{{ $payment->invoice?->number ?? $formatter::label('Unallocated payment', $settings->locale) }}</p></td><td class="value">{{ $formatter::money($payment->amount, $payment->currency, $settings->locale) }}</td></tr></table>

    @if($payment->ledger_amount !== null && ($payment->ledger_currency !== $payment->currency || $payment->fx_rate_overridden || $payment->reference))
        <table class="meta">
            <tr><td><h2>{{ $formatter::label('Ledger equivalent', $settings->locale) }}</h2><p>{{ $formatter::money($payment->ledger_amount, $payment->ledger_currency, $settings->locale) }}</p></td><td class="header-right"><h2>{{ $formatter::label('Base equivalent', $settings->locale) }}</h2><p>{{ $formatter::money($payment->base_amount ?? $payment->ledger_amount, data_get($payment->metadata, 'base_currency', $payment->ledger_currency), $settings->locale) }}</p></td></tr>
            @if($payment->reference || $payment->fx_rate_overridden)<tr><td><h2>{{ $formatter::label('Reference', $settings->locale) }}</h2><p>{{ $payment->reference ?? '—' }}</p></td><td class="header-right"><h2>{{ $formatter::label('FX rate', $settings->locale) }}</h2><p>{{ $payment->fx_rate_overridden ? $payment->fx_rate_numerator.'/'.$payment->fx_rate_denominator.' · '.$payment->fx_override_reason : $formatter::label('Current rate', $settings->locale) }}</p></td></tr>@endif
        </table>
    @elseif($payment->reference)
        <p class="muted">Reference: {{ $payment->reference }}</p>
    @endif

    @if($payment->allocations->isNotEmpty())
        <h2>{{ $formatter::label('Invoice allocations', $settings->locale) }}</h2>
        <table class="allocations">
            <thead><tr><th>{{ $formatter::label('Invoice', $settings->locale) }}</th><th class="number">{{ $formatter::label('Amount', $settings->locale) }}</th></tr></thead>
            <tbody>@foreach($payment->allocations as $allocation)<tr><td>{{ $allocation->invoice->number }}</td><td class="number">{{ $formatter::money($allocation->amount, $allocation->currency, $settings->locale) }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    <p class="footer">{{ $formatter::label('Generated', $settings->locale) }} {{ $formatter::date(now(), $settings->timezone) }} · {{ $tenant->name }}</p>
</body>
</html>

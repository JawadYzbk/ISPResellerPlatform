<!doctype html>
<html lang="{{ $settings->locale }}" dir="{{ $settings->rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #182522; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 24px; letter-spacing: .04em; }
        h2 { color: #1b6b63; font-size: 12px; margin-bottom: 5px; text-transform: uppercase; }
        .header, .meta, .summary { width: 100%; }
        .header td, .meta td, .summary td { vertical-align: top; }
        .header { border-bottom: 2px solid #1b6b63; margin-bottom: 22px; padding-bottom: 12px; }
        .header-right { text-align: right; }
        .muted { color: #70807c; }
        .meta { margin-bottom: 22px; }
        .meta td { width: 50%; }
        table.lines { border-collapse: collapse; margin-bottom: 18px; width: 100%; }
        .lines th { background: #e5f1ed; color: #1b6b63; font-size: 10px; padding: 8px; text-align: left; text-transform: uppercase; }
        .lines td { border-bottom: 1px solid #e4e9e5; padding: 8px; }
        .number { text-align: right; }
        .summary { margin-left: auto; width: 46%; }
        .summary td { border-bottom: 1px solid #e4e9e5; padding: 6px 0; }
        .summary .total td { border-bottom: 2px solid #1b6b63; color: #1b6b63; font-size: 14px; font-weight: bold; }
        .summary .label { padding-right: 12px; }
        .summary .value { text-align: right; }
        .footer { border-top: 1px solid #e4e9e5; color: #70807c; font-size: 9px; margin-top: 35px; padding-top: 8px; }
        [dir="rtl"] .header-right, [dir="rtl"] .number, [dir="rtl"] .summary .value { text-align: left; }
        [dir="rtl"] .lines th { text-align: right; }
        [dir="rtl"] .summary { margin-left: 0; margin-right: auto; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><h1>{{ $tenant->name }}</h1><p class="muted">{{ $tenant->slug }}</p></td>
            <td class="header-right"><h1>Invoice</h1><p>{{ $invoice->number }}</p><p class="muted">{{ ucfirst($invoice->status->value) }}</p></td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td><h2>Bill to</h2><p>{{ $invoice->customer->full_name }}</p><p class="muted">{{ $invoice->customer->code }}</p><p class="muted">{{ $invoice->customer->email ?? $invoice->customer->phone }}</p></td>
            <td class="header-right"><h2>Dates</h2><p>Issued: {{ $formatter::date($invoice->issued_at, $settings->timezone) }}</p><p>Due: {{ $formatter::date($invoice->due_at, $settings->timezone) }}</p></td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th>Description</th><th class="number">Qty</th><th class="number">Unit</th><th class="number">Amount</th></tr></thead>
        <tbody>
        @foreach($invoice->lines as $line)
            <tr><td>{{ $line->description }}</td><td class="number">{{ $line->quantity }}</td><td class="number">{{ $formatter::money($line->unit_amount, $line->currency) }}</td><td class="number">{{ $formatter::money($line->total_amount, $line->currency) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr><td class="label">Subtotal</td><td class="value">{{ $formatter::money($invoice->subtotal_amount, $invoice->currency) }}</td></tr>
        <tr><td class="label">Tax</td><td class="value">{{ $formatter::money($invoice->tax_amount, $invoice->currency) }}</td></tr>
        <tr class="total"><td class="label">Total</td><td class="value">{{ $formatter::money($invoice->total_amount, $invoice->currency) }}</td></tr>
        <tr><td class="label">Paid</td><td class="value">{{ $formatter::money($allocated, $invoice->currency) }}</td></tr>
        <tr><td class="label">Credits</td><td class="value">{{ $formatter::money($credited, $invoice->currency) }}</td></tr>
        <tr><td class="label">Outstanding</td><td class="value">{{ $formatter::money($outstanding, $invoice->currency) }}</td></tr>
    </table>

    <p class="footer">Generated {{ $formatter::date(now(), $settings->timezone) }} · {{ $tenant->name }}</p>
</body>
</html>

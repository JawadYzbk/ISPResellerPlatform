<!doctype html>
<html lang="{{ $settings->locale }}" dir="{{ $settings->rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->number }}</title>
    <style>
        @page { margin: {{ $widthMm === 58 ? '9px' : '14px' }}; }
        * { box-sizing: border-box; }
        body { color: #111; font-family: DejaVu Sans, sans-serif; font-size: {{ $widthMm === 58 ? '8px' : '9px' }}; line-height: 1.35; margin: 0; }
        h1, p { margin: 0; }
        .center { text-align: center; }
        .logo { height: 34px; margin: 0 auto 6px; max-width: 90px; object-fit: contain; }
        .tenant { font-size: {{ $widthMm === 58 ? '12px' : '14px' }}; font-weight: bold; }
        .muted { color: #555; }
        .rule { border-top: 1px dashed #555; margin: 10px 0; }
        .row { clear: both; min-height: 16px; }
        .label { float: {{ $settings->rtl ? 'right' : 'left' }}; max-width: 52%; }
        .value { float: {{ $settings->rtl ? 'left' : 'right' }}; font-weight: bold; max-width: 48%; text-align: {{ $settings->rtl ? 'left' : 'right' }}; }
        .amount { font-size: {{ $widthMm === 58 ? '16px' : '19px' }}; font-weight: bold; margin: 8px 0; text-align: center; }
        .allocation { border-top: 1px dotted #888; padding: 5px 0; }
        .footer { margin-top: 12px; text-align: center; }
        .status { border: 1px solid #111; display: inline-block; font-size: 8px; font-weight: bold; margin-top: 5px; padding: 2px 5px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="center">
        @if ($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="">
        @endif
        <h1 class="tenant">{{ $tenant->name }}</h1>
        <p class="muted">Payment receipt</p>
        <p class="status">{{ $payment->status->value }}</p>
    </div>

    <div class="rule"></div>
    <div class="row"><span class="label">Receipt</span><span class="value">{{ $payment->number }}</span></div>
    <div class="row"><span class="label">Date</span><span class="value">{{ $formatter::date($payment->received_at, $settings->timezone) }}</span></div>
    <div class="row"><span class="label">Customer</span><span class="value">{{ $payment->customer->full_name }}</span></div>
    <div class="row"><span class="label">Account</span><span class="value">{{ $payment->customer->code }}</span></div>
    <div class="row"><span class="label">Method</span><span class="value">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</span></div>
    @if ($payment->reference)
        <div class="row"><span class="label">Reference</span><span class="value">{{ $payment->reference }}</span></div>
    @endif

    <div class="rule"></div>
    <p class="center muted">Amount received</p>
    <p class="amount">{{ $formatter::money($payment->amount, $payment->currency) }}</p>

    @if ($payment->allocations->isNotEmpty())
        <div class="rule"></div>
        <p class="center muted" style="margin-bottom: 5px;">Applied to</p>
        @foreach ($payment->allocations as $allocation)
            <div class="allocation row">
                <span class="label">{{ $allocation->invoice->number }}</span>
                <span class="value">{{ $formatter::money($allocation->amount, $allocation->currency) }}</span>
            </div>
        @endforeach
    @endif

    <div class="rule"></div>
    <div class="footer">
        <p>Thank you.</p>
        <p class="muted">Keep this receipt for your records.</p>
        @if ($payment->status->value === 'reversed')
            <p style="font-weight: bold; margin-top: 6px;">REVERSED — NOT VALID FOR PAYMENT</p>
        @endif
    </div>
</body>
</html>

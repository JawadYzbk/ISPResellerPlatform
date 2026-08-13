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
        <p class="muted">{{ $formatter::label('Payment receipt', $settings->locale) }}</p>
        <p class="status">{{ $formatter::label($payment->status->value, $settings->locale) }}</p>
    </div>

    <div class="rule"></div>
    <div class="row"><span class="label">{{ $formatter::label('Receipt number', $settings->locale) }}</span><span class="value">{{ $payment->number }}</span></div>
    <div class="row"><span class="label">{{ $formatter::label('Date', $settings->locale) }}</span><span class="value">{{ $formatter::date($payment->received_at, $settings->timezone) }}</span></div>
    <div class="row"><span class="label">{{ $formatter::label('Customer', $settings->locale) }}</span><span class="value">{{ $payment->customer->full_name }}</span></div>
    <div class="row"><span class="label">{{ $formatter::label('Account', $settings->locale) }}</span><span class="value">{{ $payment->customer->code }}</span></div>
    <div class="row"><span class="label">{{ $formatter::label('Method', $settings->locale) }}</span><span class="value">{{ $formatter::label($payment->method, $settings->locale) }}</span></div>
    @if ($payment->reference)
        <div class="row"><span class="label">{{ $formatter::label('Reference', $settings->locale) }}</span><span class="value">{{ $payment->reference }}</span></div>
    @endif

    <div class="rule"></div>
    <p class="center muted">{{ $formatter::label('Amount received', $settings->locale) }}</p>
    <p class="amount">{{ $formatter::money($payment->amount, $payment->currency, $settings->locale) }}</p>

    @if ($payment->allocations->isNotEmpty())
        <div class="rule"></div>
        <p class="center muted" style="margin-bottom: 5px;">{{ $formatter::label('Applied to', $settings->locale) }}</p>
        @foreach ($payment->allocations as $allocation)
            <div class="allocation row">
                <span class="label">{{ $allocation->invoice->number }}</span>
                <span class="value">{{ $formatter::money($allocation->amount, $allocation->currency, $settings->locale) }}</span>
            </div>
        @endforeach
    @endif

    <div class="rule"></div>
    <div class="footer">
        <p>{{ $formatter::label('Thank you.', $settings->locale) }}</p>
        <p class="muted">{{ $formatter::label('Keep this receipt for your records.', $settings->locale) }}</p>
        @if ($payment->status->value === 'reversed')
            <p style="font-weight: bold; margin-top: 6px;">{{ $formatter::label('REVERSED — NOT VALID FOR PAYMENT', $settings->locale) }}</p>
        @endif
    </div>
</body>
</html>

@php
    use App\Services\Pricing\PricingService as P;
    $c = $quote->customer_snapshot;
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt {{ $payment->number }}</title>
<style>
    @font-face { font-family: 'Noto Sans'; font-weight: normal; src: url('{{ $fontRegular }}') format('truetype'); }
    @font-face { font-family: 'Noto Sans'; font-weight: bold; src: url('{{ $fontBold }}') format('truetype'); }
    @page { margin: 32px 36px; }
    * { font-family: 'Noto Sans', sans-serif; }
    body { font-size: 10.5px; color: #1f2933; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .muted { color: #6b7280; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .company-name { font-size: 17px; font-weight: bold; color: #0f766e; }
    .title { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #0f766e; }
    .rule { border-top: 2px solid #0f766e; margin: 12px 0; }
    .box { border: 1px solid #d1d5db; padding: 12px 14px; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
    .amount { font-size: 26px; font-weight: bold; color: #0f766e; }
    .rows td { padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 60%;">
            @if ($logo)<img src="{{ $logo }}" style="max-height: 50px; max-width: 170px; margin-bottom: 4px;"><br>@endif
            <div class="company-name">{{ $company->name }}</div>
            <div style="white-space: pre-line;">{{ $company->address }}</div>
            <div>{{ $company->phone }} @if($company->email) · {{ $company->email }} @endif</div>
            @if ($company->gstin)<div class="bold">GSTIN: {{ $company->gstin }}</div>@endif
        </td>
        <td class="right">
            <div class="title">PAYMENT RECEIPT</div>
            <div style="margin-top: 6px;"><span class="muted">Receipt no.</span> <span class="bold">{{ $payment->number }}</span></div>
            <div><span class="muted">Date</span> {{ $payment->paid_at->format('d M Y, h:i A') }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>

<table>
    <tr>
        <td style="width: 55%; padding-right: 10px;">
            <div class="box">
                <div class="label">Received from</div>
                <div class="bold" style="font-size: 12px;">{{ $c['name'] }}</div>
                <div>{{ $c['phone'] }}@if (!empty($c['email'])) · {{ $c['email'] }}@endif</div>
                <div>{{ $c['address'] }}</div>
            </div>
        </td>
        <td style="width: 45%;">
            <div class="box">
                <div class="label">Amount received</div>
                <div class="amount">{{ P::money($payment->amount) }}</div>
                <div>{{ \App\Support\IndianWords::rupees($payment->amount) }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="rows" style="margin-top: 14px;">
    <tr><td class="muted" style="width: 40%;">Towards</td><td>{{ $payment->purpose_label }} for quotation {{ $quote->display_number }}</td></tr>
    <tr><td class="muted">Payment mode</td><td>{{ $payment->gateway_label }}{{ $payment->method ? ' · '.strtoupper($payment->method) : '' }}</td></tr>
    @if ($payment->gateway_payment_id && ! str_starts_with($payment->gateway_payment_id, 'offline_'))
        <tr><td class="muted">Transaction id</td><td>{{ $payment->gateway_payment_id }}</td></tr>
    @endif
    @if ($payment->reference)
        <tr><td class="muted">Reference</td><td>{{ $payment->reference }}</td></tr>
    @endif
    <tr><td class="muted">Quotation total</td><td>{{ P::money($quote->total) }}</td></tr>
    <tr><td class="muted">Total received to date</td><td>{{ P::money($quote->amount_paid) }}</td></tr>
    <tr><td class="bold">Balance due</td><td class="bold">{{ P::money($quote->balance()) }}</td></tr>
</table>

@if ($payment->gateway === 'test')
    <p style="margin-top: 16px; color: #b45309;" class="bold">TEST MODE: this receipt is for a simulated payment. No money was transferred.</p>
@endif

<table style="margin-top: 40px;">
    <tr>
        <td class="muted">This is a computer-generated receipt.</td>
        <td class="right">For <span class="bold">{{ $company->name }}</span><br><br><br>{{ $company->signatory ?: 'Authorised Signatory' }}</td>
    </tr>
</table>
</body>
</html>

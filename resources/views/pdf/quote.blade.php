@php
    use App\Services\Pricing\PricingService as P;
    $c = $quote->customer_snapshot;
    $title = $invoice ? 'TAX INVOICE' : 'QUOTATION';
    $half = rtrim(rtrim(number_format($quote->gst_pct / 2, 2), '0'), '.');
    $full = rtrim(rtrim(number_format($quote->gst_pct, 2), '0'), '.');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $invoice?->number ?? $quote->display_number }}</title>
<style>
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: normal; src: url('{{ $fontRegular }}') format('truetype'); }
    @font-face { font-family: 'Noto Sans'; font-style: normal; font-weight: bold; src: url('{{ $fontBold }}') format('truetype'); }
    @page { margin: 28px 32px 48px 32px; }
    * { font-family: 'Noto Sans', sans-serif; }
    body { font-size: 10px; color: #1f2933; line-height: 1.35; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .muted { color: #6b7280; }
    .small { font-size: 8.5px; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .brand { color: #0f766e; }
    .head td { padding: 0; }
    .company-name { font-size: 17px; font-weight: bold; color: #0f766e; }
    .doc-title { font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #0f766e; }
    .meta td { padding: 1px 0; }
    .rule { border-top: 2px solid #0f766e; margin: 10px 0 10px; }
    .box { border: 1px solid #d1d5db; padding: 8px 10px; }
    .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 2px; }
    .items th.right { text-align: right; }
    .items th { background: #0f766e; color: #fff; font-weight: bold; font-size: 9px; padding: 6px 5px; text-align: left; }
    .items td { padding: 6px 5px; border-bottom: 1px solid #e5e7eb; }
    .items tr.alt td { background: #f8fafb; }
    .formula { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .tag { font-size: 7.5px; color: #92400e; background: #fef3c7; padding: 1px 4px; }
    .totals td { padding: 3px 6px; }
    .totals .grand td { border-top: 2px solid #0f766e; font-size: 13px; font-weight: bold; color: #0f766e; padding-top: 6px; }
    .thumb { width: 70px; margin-top: 4px; border: 1px solid #e5e7eb; }
    .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    .terms { font-size: 8.5px; white-space: pre-line; }
    .sign { height: 46px; }
</style>
</head>
<body>

<div class="footer">{{ $company->name }} · {{ $company->phone }} · {{ $company->email }} · {{ $invoice?->number ?? $quote->display_number }}</div>

<table class="head">
    <tr>
        <td style="width: 60%;">
            @if ($logo)
                <img src="{{ $logo }}" style="max-height: 54px; max-width: 180px; margin-bottom: 4px;"><br>
            @endif
            <div class="company-name">{{ $company->name }}</div>
            <div style="white-space: pre-line;">{{ $company->address }}</div>
            <div>{{ $company->stateName() }} ({{ $company->state_code }})</div>
            <div>{{ $company->phone }} @if($company->email) · {{ $company->email }} @endif</div>
            @if ($company->gstin)<div class="bold">GSTIN: {{ $company->gstin }}</div>@endif
        </td>
        <td style="width: 40%;" class="right">
            <div class="doc-title">{{ $title }}</div>
            <table class="meta" style="margin-top: 6px;">
                @if ($invoice)
                    <tr><td class="muted right">Invoice no.</td><td class="right bold" style="width: 55%;">{{ $invoice->number }}</td></tr>
                    <tr><td class="muted right">Invoice date</td><td class="right">{{ $invoice->date->format('d M Y') }}</td></tr>
                    <tr><td class="muted right">Quotation ref.</td><td class="right">{{ $quote->display_number }}</td></tr>
                @else
                    <tr><td class="muted right">Quotation no.</td><td class="right bold" style="width: 55%;">{{ $quote->display_number }}</td></tr>
                    <tr><td class="muted right">Date</td><td class="right">{{ $quote->date->format('d M Y') }}</td></tr>
                    <tr><td class="muted right">Valid till</td><td class="right">{{ $quote->valid_till->format('d M Y') }}</td></tr>
                @endif
                @if ($quote->creator)
                    <tr><td class="muted right">Prepared by</td><td class="right">{{ $quote->creator->name }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="rule"></div>

<table>
    <tr>
        <td style="width: 50%; padding-right: 6px;">
            <div class="box">
                <div class="label">{{ $invoice ? 'Bill to' : 'Quotation for' }}</div>
                <div class="bold" style="font-size: 11px;">{{ $c['name'] }}</div>
                <div>{{ $c['phone'] }}@if (!empty($c['email'])) · {{ $c['email'] }}@endif</div>
                @if (!empty($c['gstin']))<div>GSTIN: {{ $c['gstin'] }}</div>@endif
            </div>
        </td>
        <td style="width: 50%; padding-left: 6px;">
            <div class="box">
                <div class="label">Site address{{ !empty($c['site_label']) ? ' · '.$c['site_label'] : '' }}</div>
                <div>{{ $c['address'] }}</div>
                <div>Place of supply: <span class="bold">{{ $c['state_name'] }} ({{ $quote->place_of_supply }})</span></div>
            </div>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 12px;">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 26%;">Item</th>
            <th style="width: 38%;">Specification</th>
            <th style="width: 6%;" class="right">Qty</th>
            <th style="width: 11%;" class="right">Area</th>
            <th style="width: 15%;" class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quote->lines as $i => $line)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td>
                    <div class="bold">{{ $line->name }}</div>
                    @if ($line->product_name !== $line->name)<div class="muted small">{{ $line->product_name }}</div>@endif
                    @if (!empty($photos[$line->id]))
                        <img class="thumb" src="{{ $photos[$line->id] }}">
                    @endif
                </td>
                <td>
                    <div>{{ $line->specification() }}</div>
                    @if ($summary = $line->inputSummary())<div class="small muted">{{ $summary }}</div>@endif
                    <div class="small muted">{{ $line->area_text }}</div>
                    <div class="formula">{{ $line->formula }}</div>
                </td>
                <td class="right">{{ P::num($line->qty) }}</td>
                <td class="right">{{ P::num($line->area * $line->qty) }} {{ $line->measure_unit }}</td>
                <td class="right">
                    <div class="bold">{{ P::money($line->amount) }}</div>
                    @if ($line->min_applied)
                        <div style="margin-top: 3px;"><span class="tag">Min. item charge</span></div>
                        <div class="small muted">calc. {{ P::money($line->raw_amount) }}</div>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 10px;">
    <tr>
        <td style="width: 55%; padding-right: 14px;">
            <div class="label">Amount in words</div>
            <div class="bold" style="margin-bottom: 10px;">{{ \App\Support\IndianWords::rupees($quote->total) }}</div>
            @if ($quote->min_job_adjustment > 0 || $quote->lines->contains('min_applied', true))
                <div class="small muted" style="margin-bottom: 8px;">
                    @if ($quote->lines->contains('min_applied', true))
                        Minimum charge per item: {{ P::money($quote->min_item) }}.
                    @endif
                    @if ($quote->min_job_adjustment > 0)
                        Minimum job charge: {{ P::money($quote->min_job) }}.
                    @endif
                </div>
            @endif
            @if ($quote->notes)
                <div class="label">Notes</div>
                <div style="white-space: pre-line; margin-bottom: 8px;">{{ $quote->notes }}</div>
            @endif
        </td>
        <td style="width: 45%;">
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ P::money($quote->subtotal) }}</td></tr>
                @if ($quote->discount_amount > 0)
                    <tr><td>Discount{{ $quote->discount_type === 'percent' ? ' ('.P::num($quote->discount_value).'%)' : '' }}</td><td class="right">- {{ P::money($quote->discount_amount) }}</td></tr>
                @endif
                @if ($quote->visit_charge > 0)
                    <tr><td>Site visit charge</td><td class="right">{{ P::money($quote->visit_charge) }}</td></tr>
                @endif
                @if ($quote->min_job_adjustment > 0)
                    <tr><td>Minimum job charge adjustment</td><td class="right">{{ P::money($quote->min_job_adjustment) }}</td></tr>
                @endif
                <tr><td class="bold">Taxable value</td><td class="right bold">{{ P::money($quote->taxable) }}</td></tr>
                @if ($quote->intra_state)
                    <tr><td>CGST @ {{ $half }}%</td><td class="right">{{ P::money($quote->cgst) }}</td></tr>
                    <tr><td>SGST @ {{ $half }}%</td><td class="right">{{ P::money($quote->sgst) }}</td></tr>
                @else
                    <tr><td>IGST @ {{ $full }}%</td><td class="right">{{ P::money($quote->igst) }}</td></tr>
                @endif
                <tr class="grand"><td>Grand total</td><td class="right">{{ P::money($quote->total) }}</td></tr>
                @if ($quote->amount_paid > 0)
                    <tr><td>Amount received</td><td class="right">- {{ P::money($quote->amount_paid) }}</td></tr>
                    <tr><td class="bold">Balance due</td><td class="right bold">{{ P::money($quote->balance()) }}</td></tr>
                @elseif (! $invoice && $quote->advance_pct > 0)
                    <tr><td class="muted small">Advance to confirm ({{ P::num($quote->advance_pct) }}%)</td><td class="right muted small">{{ P::money($quote->advanceAmount()) }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table style="margin-top: 16px; page-break-inside: avoid;">
    <tr>
        <td style="width: 60%; padding-right: 14px;">
            @if ($company->terms)
                <div class="label">Terms &amp; conditions</div>
                <div class="terms">{{ $company->terms }}</div>
            @endif
            @if ($company->bank_details)
                <div class="label" style="margin-top: 8px;">Bank details</div>
                <div class="terms">{{ $company->bank_details }}</div>
            @endif
        </td>
        <td style="width: 40%;" class="right">
            <div>For <span class="bold">{{ $company->name }}</span></div>
            <div class="sign"></div>
            <div style="border-top: 1px solid #9ca3af; display: inline-block; padding-top: 3px; min-width: 160px;">
                {{ $company->signatory ?: 'Authorised Signatory' }}
            </div>
            @unless ($invoice)
                <div style="margin-top: 26px;" class="small muted">Customer acceptance</div>
                <div class="sign"></div>
                <div style="border-top: 1px solid #9ca3af; display: inline-block; padding-top: 3px; min-width: 160px;">Signature &amp; date</div>
            @endunless
        </td>
    </tr>
</table>

</body>
</html>

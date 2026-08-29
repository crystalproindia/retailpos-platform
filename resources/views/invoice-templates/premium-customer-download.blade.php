@php
    $receivable = $render['receivable'];
    $lineDiscount = (float) $invoice->discount_total - (float) $invoice->overall_discount_total;
    $projectTitle = $invoice->opportunity?->title ?: $invoice->quotation?->title;
    $projectDescription = $invoice->opportunity?->description;
@endphp
<!doctype html>
<html>
<head>
    <style>
        @page { margin: 8mm; }
        body { color:#17223a; font-family:DejaVu Sans; font-size:8.4px; line-height:1.38; }
        table { border-collapse:collapse; width:100%; } th,td { vertical-align:top; } .right { text-align:right; }
        .invoice-watermark { height:38%; left:15%; opacity:{{ data_get($render, 'watermark.opacity', 0.12) }}; pointer-events:none; position:fixed; text-align:center; top:31%; width:70%; z-index:0; }
        .invoice-watermark img { display:inline-block; height:100%; max-width:100%; object-fit:contain; }
        .muted { color:#5b6b84; font-size:7.6px; } .accent { color:#1760d3; }
        .top-rule { background:#0c2d5c; height:2px; margin:-8mm -8mm 12px; }
        .header { padding-bottom:14px; } .company-name { color:#071631; font-size:20px; font-weight:bold; line-height:1.05; }
        .company-details { color:#24334d; font-size:8.6px; line-height:1.6; margin-top:13px; } .company-details strong { font-size:10px; }
        .document-title { color:#061631; font-size:23px; font-weight:bold; letter-spacing:.2px; line-height:1; }
        .invoice-number { background:#061b41; border-radius:3px; color:#fff; display:inline-block; font-size:12px; font-weight:bold; margin-top:10px; padding:9px 13px; }
        .meta { margin-top:14px; } .meta td { border-bottom:1px solid #dbe3ef; padding:7px 0; } .meta td:first-child { width:25px; color:#071d43; font-size:11px; }
        .meta td:last-child { text-align:right; font-weight:bold; } .meta .due { color:#ef1e26; }
        .rule { border:0; border-top:1px solid #c5d5ed; margin:0 0 13px; }
        .party { margin-bottom:14px; page-break-inside:avoid; } .party td { padding:0 12px 0 0; } .party td + td { border-left:1px solid #c7d7ec; padding-left:22px; }
        .eyebrow { color:#0c5cd4; font-size:8px; font-weight:bold; letter-spacing:.25px; text-transform:uppercase; }
        .party-icon { background:#eaf2ff; border-radius:50%; color:#0c5cd4; display:inline-block; font-size:16px; height:31px; line-height:31px; margin-right:10px; text-align:center; width:31px; }
        .party-title { font-size:10px; font-weight:bold; } .party-copy { display:inline-block; line-height:1.45; vertical-align:top; width:calc(100% - 46px); }
        .items { border:1px solid #cad9ef; border-radius:4px; margin-top:4px; overflow:hidden; } .items thead { display:table-header-group; } .items tr { page-break-inside:avoid; }
        .items th { background:#245fb9; color:#fff; font-size:7.4px; padding:7px 5px; } .items td { border-bottom:1px solid #dbe4f1; border-right:1px solid #e3eaf4; padding:7px 5px; }
        .items tr:last-child td { border-bottom:0; } .items td:last-child { border-right:0; } .items .description { width:41%; } .items .numeric { text-align:right; white-space:nowrap; }
        .item-name { color:#111d34; font-weight:bold; } .after-items { margin-top:11px; page-break-inside:avoid; } .amount-words { padding:8px 8px 8px 0; }
        .amount-words strong { color:#536176; display:block; font-size:8px; font-style:italic; font-weight:normal; line-height:1.45; margin-top:4px; }
        .totals td { padding:3px 7px; } .totals .blue-line td { border-top:1px solid #1760d3; color:#0d56c3; font-size:10px; font-weight:bold; padding-top:7px; }
        .finance { border:1px solid #78a8f4; border-radius:5px; margin-top:14px; page-break-inside:avoid; } .finance-heading { color:#0c55c9; font-size:9px; font-weight:bold; padding:10px 13px 8px; }
        .finance td { border-right:1px solid #d8e4f5; padding:8px 10px; } .finance td:last-child { border-right:0; } .finance small { color:#364862; font-size:7px; } .finance strong { display:block; font-size:9px; margin-top:3px; }
        .finance .balance-wrap { padding:8px 14px; } .balance-box { border:1px solid #8bb3f4; border-radius:4px; color:#0d5ed7; padding:11px 12px; text-align:center; } .balance-box strong { font-size:17px; line-height:1.1; }
        .status-unpaid { color:#ef1e26; } .status-paid { color:#0b8d5a; } .status-partial { color:#bc6f00; }
        .payment-details { border:1px solid #d7e2ef; margin-top:10px; padding:7px 10px; page-break-inside:avoid; } .payment-details > strong { color:#0c55c9; font-size:8px; text-transform:uppercase; }
        .payment-details td { border:0; padding:2px 9px 2px 0; } .payment-details td:first-child { color:#64748b; width:25%; } .payment-details-value { overflow-wrap:anywhere; word-break:break-word; }
        .completion { margin-top:13px; page-break-inside:avoid; } .notes { border-left:2px solid #2165d0; padding-left:9px; } .notes strong { color:#0d56c3; } .signature { text-align:right; }
        .signature img { display:block; margin:7px 0 3px auto; max-height:40px; max-width:120px; } .signature-line { border-top:1px solid #8aa6d5; display:inline-block; margin-top:31px; min-width:150px; padding-top:4px; text-align:center; }
        .support { border-top:1px solid #b9cbe5; margin-top:10px; padding:8px 0; } .support td { padding:0 12px; } .support td + td { border-left:1px solid #ccd9eb; } .support strong { color:#17223a; display:block; font-size:8px; }
        .bottom { background:#06224a; color:#fff; font-size:7px; margin:0 -8mm -8mm; padding:7px 8mm; text-align:center; } .page-break { page-break-before:always; }
    </style>
</head>
<body>
@include('invoice-templates.partials.watermark')
<div class="top-rule"></div>
<table class="header" role="presentation"><tr>
    <td width="61%">
        @include('invoice-templates.partials.company-branding', ['nameClass' => 'company-name'])
        <div class="company-details"><strong>{{ $invoice->company->legal_name ?: $invoice->company->name }}</strong><br>{{ $invoice->company->address }}@if($invoice->company->phone)<br>{{ $invoice->company->phone }}@endif @if($invoice->company->email)<br>{{ $invoice->company->email }}@endif @if($render['is_gst'] && $invoice->supplier_gstin_snapshot)<br>GSTIN: {{ $invoice->supplier_gstin_snapshot }}@endif</div>
    </td>
    <td class="right">
        <div class="document-title">{{ $render['document_title'] }}</div><div class="invoice-number">{{ $invoice->invoice_number }}</div>
        <table class="meta" role="presentation"><tr><td>▣</td><td>Invoice Date</td><td>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td></tr><tr><td>▣</td><td>Due Date</td><td class="{{ $invoice->due_date?->isPast() && $receivable['balance_receivable'] > 0 ? 'due' : '' }}">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td></tr><tr><td>◇</td><td>Currency</td><td>{{ $invoice->currency }}{{ $invoice->currency === 'INR' ? ' (Indian Rupee)' : '' }}</td></tr></table>
    </td>
</tr></table>
<hr class="rule">

<table class="party" role="presentation"><tr>
    <td width="44%"><div class="eyebrow">Billed to</div><div style="margin-top:10px"><span class="party-icon">○</span><span class="party-copy"><span class="party-title">{{ $invoice->billing_company ?: $invoice->billing_name }}</span><br>@if($invoice->billing_company && $invoice->billing_name){{ $invoice->billing_name }}<br>@endif{{ $invoice->billing_address }}@if($invoice->billing_email)<br>Email: {{ $invoice->billing_email }}@endif @if($render['is_gst'] && $invoice->customer_tax_number)<br>GSTIN: {{ $invoice->customer_tax_number }}@endif</span></div></td>
    <td><div class="eyebrow">Project / Services</div><div style="margin-top:10px"><span class="party-icon">▣</span><span class="party-copy"><span class="party-title">{{ $projectTitle ?: ($invoice->items->first()?->name ?: 'Invoice services') }}</span><br>{{ $projectDescription ?: 'Products and services supplied as detailed below.' }}@if($invoice->quotation)<br><em>Reference: {{ $invoice->quotation->quotation_number }}</em>@endif</span></div></td>
</tr></table>

@foreach($render['item_chunks'] as $chunkIndex => $items)
    @if($chunkIndex > 0)<div class="page-break"></div>@endif
    <table class="items" role="presentation"><thead><tr><th width="4%">#</th><th class="description">ITEM / DESCRIPTION</th><th class="numeric" width="8%">QTY</th><th class="numeric" width="13%">RATE ({{ $invoice->currency }})</th><th class="numeric" width="12%">DISCOUNT</th><th class="numeric" width="10%">TAX</th><th class="numeric" width="13%">LINE TOTAL</th></tr></thead><tbody>
        @foreach($items as $item)<tr><td>{{ ($chunkIndex * 50) + $loop->iteration }}</td><td class="description"><span class="item-name">{{ $item->name }}</span>@if($item->description)<br><span class="muted">{{ $item->description }}</span>@endif</td><td class="numeric">{{ number_format((float) $item->quantity, 3) }}<br><span class="muted">{{ $item->unit }}</span></td><td class="numeric">{{ number_format((float) $item->unit_price, 2) }}</td><td class="numeric">{{ number_format((float) $item->discount_amount, 2) }}</td><td class="numeric">{{ number_format((float) $item->tax_amount, 2) }}</td><td class="numeric"><strong>{{ number_format((float) $item->line_total, 2) }}</strong></td></tr>@endforeach
    </tbody></table>
@endforeach

<table class="after-items" role="presentation"><tr><td width="59%">@if(data_get($render, 'setting.options.show_amount_words', true))<div class="amount-words"><span class="eyebrow">Amount in words</span><strong>{{ $render['amount_in_words'] }}</strong></div>@endif</td><td><table class="totals" role="presentation"><tr><td>Subtotal</td><td class="right">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>@if($lineDiscount !== 0.0)<tr><td>Line Discount</td><td class="right">{{ number_format($lineDiscount, 2) }}</td></tr>@endif @if((float) $invoice->overall_discount_total !== 0.0)<tr><td>Overall Invoice Discount</td><td class="right">{{ number_format((float) $invoice->overall_discount_total, 2) }}</td></tr>@endif @if((float) $invoice->tax_total !== 0.0)<tr><td>Total Tax</td><td class="right">{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>@endif @if((float) $invoice->adjustment_total !== 0.0)<tr><td>Round Off</td><td class="right">{{ number_format((float) $invoice->adjustment_total, 2) }}</td></tr>@endif<tr class="blue-line"><td>TOTAL INVOICE AMOUNT</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td></tr></table></td></tr></table>

<section class="finance"><div class="finance-heading">▤ &nbsp; PAYMENT &amp; RECEIVABLES SUMMARY</div><table role="presentation"><tr><td width="21%"><small>Invoice Date</small><strong>{{ $receivable['invoice_date']?->format('d M Y') ?? '—' }}</strong><br><small>Due Date</small><strong class="{{ $invoice->due_date?->isPast() && $receivable['balance_receivable'] > 0 ? 'status-unpaid' : '' }}">{{ $receivable['due_date']?->format('d M Y') ?? '—' }}</strong></td><td width="22%"><small>Total Invoice Amount</small><strong>{{ $invoice->currency }} {{ number_format($receivable['invoice_total'] / 100, 2) }}</strong><br><small>Amount Received</small><strong>{{ $invoice->currency }} {{ number_format($receivable['amount_received'] / 100, 2) }}</strong></td><td width="30%" class="balance-wrap"><div class="balance-box"><small>BALANCE RECEIVABLE</small><strong>{{ $invoice->currency }} {{ number_format($receivable['balance_receivable'] / 100, 2) }}</strong><small class="{{ strtolower($receivable['payment_status']) === 'paid' ? 'status-paid' : (strtolower($receivable['payment_status']) === 'partially paid' ? 'status-partial' : 'status-unpaid') }}">{{ strtoupper($receivable['payment_status']) }}</small></div></td><td><small>Payment Terms / Status</small><strong>{{ $invoice->due_date ? 'Due '.$invoice->due_date->format('d M Y') : 'Due on receipt' }}</strong><strong class="{{ strtolower($receivable['payment_status']) === 'paid' ? 'status-paid' : (strtolower($receivable['payment_status']) === 'partially paid' ? 'status-partial' : 'status-unpaid') }}">● {{ $receivable['payment_status'] }}</strong><br><small>Last Payment Date</small><strong>{{ $receivable['last_payment_date']?->format('d M Y') ?? '—' }}</strong></td></tr></table></section>

@include('invoice-templates.partials.payment-details')
<table class="completion" role="presentation"><tr><td class="notes" width="63%"><strong>Notes</strong><br>{{ $invoice->notes ?: ($invoice->terms_conditions ?: 'Thank you for your business. We appreciate your partnership.') }}</td><td class="signature"><strong class="accent">For {{ $invoice->company->legal_name ?: $invoice->company->name }}</strong>@if($invoice->show_authorized_signature && data_get($render, 'signature.data_uri'))<img src="{{ $render['signature']['data_uri'] }}" alt="Authorized signature">@endif<div class="signature-line">{{ data_get($render, 'signature.name') ?: 'Authorized Signatory' }} @if(data_get($render, 'signature.designation'))<br><span class="muted">{{ $render['signature']['designation'] }}</span>@endif</div></td></tr></table>
<table class="support" role="presentation"><tr><td><strong>Secure &amp; Reliable</strong>Your business, our priority.</td><td><strong>Need Help?</strong>{{ $invoice->company->email ?: $invoice->company->phone ?: 'Contact your account team' }}</td><td><strong>Visit Us</strong>{{ $invoice->company->address }}</td></tr></table>
<div class="bottom">This is a computer generated invoice and does not require a signature.</div>
</body>
</html>

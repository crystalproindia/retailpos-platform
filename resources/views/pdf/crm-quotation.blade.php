<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 30px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; }
        .brand { border-bottom: 2px solid #0f766e; padding-bottom: 18px; }
        .brand-table, .summary-table, .items { width: 100%; border-collapse: collapse; }
        .eyebrow { color: #0f766e; font-size: 9px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { font-size: 25px; line-height: 1.1; margin: 4px 0 0; }
        h2 { font-size: 11px; margin: 0 0 7px; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .section { margin-top: 22px; }
        .panel { background: #f8fafc; border: 1px solid #e2e8f0; padding: 13px; }
        .items th { background: #0f172a; color: #fff; font-size: 8px; letter-spacing: .5px; padding: 9px 8px; text-align: left; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 9px 8px; vertical-align: top; }
        .items .number { text-align: right; white-space: nowrap; }
        .summary { margin-left: auto; width: 250px; }
        .summary td { padding: 4px 0; }
        .summary .total td { border-top: 1px solid #cbd5e1; font-size: 13px; font-weight: bold; padding-top: 8px; }
        .footer { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 8px; margin-top: 28px; padding-top: 12px; }
        .link { color: #0f766e; font-size: 8px; overflow-wrap: break-word; }
    </style>
</head>
<body>
    <table class="brand-table brand"><tr><td><div class="eyebrow">RetailPOS.biz</div><h1>Proposal</h1><p class="muted">Powered by CrystalPro</p></td><td class="right"><strong>{{ $quotation->quotation_number }}</strong><br><span class="muted">Issued {{ $quotation->created_at?->format('d M Y') }}</span><br><span class="muted">Valid until {{ $quotation->valid_until?->format('d M Y') ?? 'Not specified' }}</span></td></tr></table>

    <table class="brand-table section"><tr><td width="50%"><h2>Prepared for</h2><strong>{{ $quotation->customer_company ?: $quotation->customer_name ?: 'Customer' }}</strong><br>{{ $quotation->customer_name }}<br>{{ $quotation->customer_email }}<br>{{ $quotation->customer_phone }}</td><td width="50%"><h2>Proposal reference</h2>{{ $quotation->title }}<br>{{ $quotation->lead?->business_name ?: $quotation->lead?->title }}@if($quotation->billing_address)<br><br><span class="muted">Billing address</span><br>{!! nl2br(e($quotation->billing_address)) !!}@endif</td></tr></table>

    <div class="section"><h2>Proposal items</h2><table class="items"><thead><tr><th>Item</th><th class="number">Qty</th><th class="number">Unit price</th><th class="number">Discount</th><th class="number">Tax</th><th class="number">Total</th></tr></thead><tbody>@foreach($quotation->items as $item)<tr><td><strong>{{ $item->name }}</strong>@if($item->description)<br><span class="muted">{{ $item->description }}</span>@endif</td><td class="number">{{ number_format((float) $item->quantity, 3) }}</td><td class="number">{{ $quotation->currency }} {{ number_format((float) $item->unit_price, 2) }}</td><td class="number">{{ $quotation->currency }} {{ number_format((float) $item->discount_amount, 2) }}</td><td class="number">{{ $quotation->currency }} {{ number_format((float) $item->tax_amount, 2) }}</td><td class="number"><strong>{{ $quotation->currency }} {{ number_format((float) $item->line_total, 2) }}</strong></td></tr>@endforeach</tbody></table></div>

    <table class="summary summary-table section"><tr><td>Subtotal</td><td class="right">{{ $quotation->currency }} {{ number_format((float) $quotation->subtotal, 2) }}</td></tr><tr><td>Discount</td><td class="right">{{ $quotation->currency }} {{ number_format((float) $quotation->discount_total, 2) }}</td></tr><tr><td>Tax</td><td class="right">{{ $quotation->currency }} {{ number_format((float) $quotation->tax_total, 2) }}</td></tr><tr class="total"><td>Grand total</td><td class="right">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</td></tr></table>

    @if($quotation->notes)<div class="panel section"><h2>Notes</h2>{!! nl2br(e($quotation->notes)) !!}</div>@endif
    @if($quotation->terms_conditions)<div class="panel section"><h2>Terms and conditions</h2>{!! nl2br(e($quotation->terms_conditions)) !!}</div>@endif
    <div class="footer">RetailPOS.biz · India +91 8072682244 · Malaysia +60 104305163 · Singapore +65 92475024 · info@retailpos.biz · global@retailpos.biz</div>
</body>
</html>

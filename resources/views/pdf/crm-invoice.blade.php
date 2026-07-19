<!doctype html>
<html>
<head>
    <style>
        @page { margin: 30px; }
        body { color: #172033; font-family: DejaVu Sans; font-size: 10px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 15px; }
        .right { text-align: right; }
        .items { border-collapse: collapse; margin-top: 22px; width: 100%; }
        .items th { background: #0f172a; color: #fff; padding: 8px; text-align: left; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
        .number { text-align: right; }
        .summary { margin-left: auto; margin-top: 20px; width: 250px; }
        .summary td { padding: 4px; }
        .total td { border-top: 1px solid #cbd5e1; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td><strong>RETAILPOS.BIZ</strong><h1>Invoice</h1></td>
            <td class="right"><strong>{{ $invoice->invoice_number }}</strong><br>Issued {{ $invoice->issue_date?->format('d M Y') }}<br>Due {{ $invoice->due_date?->format('d M Y') }}</td>
        </tr>
    </table>
    <table style="margin-top:20px" width="100%">
        <tr>
            <td width="50%"><strong>Bill to</strong><br>{{ $invoice->billing_company ?: $invoice->billing_name }}<br>{{ $invoice->billing_name }}<br>{{ $invoice->billing_email }}<br>{!! nl2br(e($invoice->billing_address)) !!}</td>
            <td><strong>Related quotation</strong><br>{{ $invoice->quotation?->quotation_number ?: '-' }}<br><strong>Tax number</strong><br>{{ $invoice->customer_tax_number ?: '-' }}</td>
        </tr>
    </table>
    <table class="items">
        <thead><tr><th>Item</th><th class="number">Qty</th><th class="number">Rate</th><th class="number">Discount</th><th class="number">Tax</th><th class="number">Total</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td><strong>{{ $item->name }}</strong><br>{{ $item->description }}</td>
                    <td class="number">{{ $item->quantity }} {{ $item->unit }}</td>
                    <td class="number">{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="number">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="number">{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="number">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="summary">
        <tr><td>Subtotal</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
        <tr><td>Adjustment</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->adjustment_total, 2) }}</td></tr>
        <tr class="total"><td>Total</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
        <tr><td>Balance due</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
    </table>
    @if ($invoice->notes)
        <p><strong>Notes</strong><br>{!! nl2br(e($invoice->notes)) !!}</p>
    @endif
    @if ($invoice->terms_conditions)
        <p><strong>Terms</strong><br>{!! nl2br(e($invoice->terms_conditions)) !!}</p>
    @endif
    <p style="border-top:1px solid #e2e8f0;margin-top:28px;padding-top:10px">RetailPOS.biz | India +91 8072682244 | Malaysia +60 104305163 | Singapore +65 92475024 | info@retailpos.biz | global@retailpos.biz</p>
</body>
</html>

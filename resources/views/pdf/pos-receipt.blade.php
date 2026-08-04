<!doctype html>
<html><head><style>@page{margin:28px}body{font-family:DejaVu Sans;font-size:10px;color:#172033}.right{text-align:right}.items{width:100%;border-collapse:collapse;margin-top:16px}.items th{background:#0f172a;color:#fff;padding:7px;text-align:left}.items td{border-bottom:1px solid #e2e8f0;padding:7px}.total{font-weight:bold;border-top:1px solid #94a3b8}</style></head>
<body>
@php($receiptLogo = ($branding['show_logo'] ?? false) ? ($branding['data_uri'] ?? null) : null)
@php($receiptSize = ['small' => ['width' => 72, 'height' => 20], 'medium' => ['width' => 96, 'height' => 28], 'large' => ['width' => 120, 'height' => 36]][$branding['logo_size'] ?? 'medium'] ?? ['width' => 96, 'height' => 28])
@php($showReceiptName = ($branding['show_company_name'] ?? true) || ! $receiptLogo)
<table width="100%"><tr><td style="text-align:{{ $branding['logo_position'] ?? 'left' }}">
@if($receiptLogo)
<img src="{{ $receiptLogo }}" alt="{{ $sale->company->name }} logo" style="display:inline-block; width:auto; height:auto; max-width:{{ $receiptSize['width'] }}px; max-height:{{ $receiptSize['height'] }}px; margin-bottom:5px"><br>
@endif
@if($showReceiptName)
<strong>{{ $gst?->legal_name ?: $sale->company->name ?? 'RetailPOS' }}</strong><br>
@endif
<h1>POS Receipt</h1><p>{{ $sale->branch?->name }}<br>{{ $sale->branch?->address }}@if($gst?->gstin)<br>GSTIN {{ $gst->gstin }}@endif</p></td><td class="right"><strong>{{ $sale->receipt_number ?: $sale->sale_number }}</strong><br>{{ $sale->completed_at?->format('d M Y, h:i A') }}<br>{{ $sale->register?->name }}</td></tr></table>
<p><strong>Customer:</strong> {{ $sale->customer_name_snapshot ?: $sale->customer?->display_name ?: 'Walk-in customer' }}</p>
<table class="items"><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td>{{ $item->product_name }} @if($item->variant_label)<br><small>{{ $item->variant_label }}</small>@endif</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ number_format((float)$item->unit_price,2) }}</td><td class="right">{{ number_format((float)$item->discount_amount,2) }}</td><td class="right">{{ number_format((float)$item->tax_amount,2) }}</td><td class="right">{{ number_format((float)$item->line_total,2) }}</td></tr>@endforeach</tbody></table>
<table style="margin-left:auto;margin-top:16px;width:240px"><tr><td>Subtotal</td><td class="right">{{ number_format((float)$sale->subtotal,2) }}</td></tr><tr><td>Discount</td><td class="right">{{ number_format((float)$sale->discount_amount,2) }}</td></tr><tr><td>Taxable</td><td class="right">{{ number_format((float)$sale->taxable_amount,2) }}</td></tr><tr><td>CGST / SGST</td><td class="right">{{ number_format((float)$sale->cgst_total,2) }} / {{ number_format((float)$sale->sgst_total,2) }}</td></tr><tr><td>IGST / Cess</td><td class="right">{{ number_format((float)$sale->igst_total,2) }} / {{ number_format((float)$sale->cess_total,2) }}</td></tr><tr class="total"><td>Total</td><td class="right">{{ number_format((float)$sale->total_amount,2) }}</td></tr><tr><td>Paid</td><td class="right">{{ number_format((float)$sale->paid_amount,2) }}</td></tr><tr><td>Change</td><td class="right">{{ number_format((float)$sale->change_amount,2) }}</td></tr></table>
<p style="margin-top:24px">Thank you for shopping with us.</p></body></html>

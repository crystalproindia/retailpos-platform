<!doctype html>
<!-- {{ $render['template']['label'] }} -->
<html><head><style>
@include('invoice-templates.partials.print-page')
body{font:9px DejaVu Sans;color:#352452}.right{text-align:right}.muted{color:#74628f;font-size:8px}table{border-collapse:collapse;width:100%}th,td{vertical-align:top}.hero{padding:18px;background:#352452;color:#fff}.brand{font-size:21px;font-weight:bold}.subtitle{color:#e9d5ff}.details{margin-top:14px}.details td{padding:9px;background:#faf5ff;border:1px solid #eadcff}.items-minimal{margin-top:15px}.items-minimal th{padding:7px;border-bottom:2px solid #7e22ce;color:#6b21a8}.items-minimal td{padding:8px;border-bottom:1px solid #eee6f6}.items-minimal thead{display:table-header-group}.footer{margin-top:16px}.totals{border:1px solid #e9d5ff}.totals td{padding:6px;border-bottom:1px solid #f3e8ff}.grand td{background:#7e22ce;color:#fff;font-weight:bold}.balance td{font-weight:bold;background:#faf5ff}.avoid-break,.items-minimal tr{page-break-inside:avoid}
</style></head><body>
<table class="hero"><tr><td>@include('invoice-templates.partials.company-branding',['nameClass'=>'brand'])<span class="subtitle">{{ $invoice->company->address }}</span></td><td class="right"><strong>{{ $render['is_gst'] ? 'TAX INVOICE' : 'INVOICE' }}</strong><br>{{ $invoice->invoice_number }}</td></tr></table>
<table class="details"><tr><td width="60%"><strong>Client</strong><br>{{ $invoice->billing_company ?: $invoice->billing_name }}<br>{{ $invoice->billing_address }}</td><td><strong>Issued</strong><br>{{ $invoice->issue_date?->format('d M Y') }}<br><strong>Due</strong><br>{{ $invoice->due_date?->format('d M Y') ?: '—' }}</td></tr></table>
@include('invoice-templates.partials.items-minimal')
<table class="footer"><tr><td width="58%">@if($render['setting']->gst_presentation==='detailed')@include('invoice-templates.partials.gst-summary')@endif @if($invoice->notes)<strong>Notes</strong><br>{{ $invoice->notes }}@endif @include('invoice-templates.partials.payment-qr')</td><td>@include('invoice-templates.partials.totals')</td></tr></table>
</body></html>

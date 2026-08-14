<!doctype html>
<!-- {{ $render['template']['label'] }} -->
<html><head><style>
@include('invoice-templates.partials.print-page')
body{font:9px DejaVu Sans;color:#243447}.right{text-align:right}.muted{color:#62748a;font-size:8px}table{border-collapse:collapse;width:100%}th,td{vertical-align:top}.bar{height:10px;background:{{ $render['template']['variant']==='contractor' ? '#b91c1c' : ($render['template']['variant']==='rental' ? '#ea580c' : '#0369a1') }};margin:-12mm -12mm 13px}.head td{padding-bottom:12px}.brand{font-size:20px;font-weight:bold}.title{font-size:18px;font-weight:bold}.facts{margin-top:9px}.facts td{border:1px solid #cbd5e1;padding:7px}.items-grid{margin-top:13px}.items-grid th,.items-grid td{border:1px solid #94a3b8;padding:5px}.items-grid th{background:#eaf1f8}.items-grid thead{display:table-header-group}.footer{margin-top:14px}.totals td{padding:5px;border-bottom:1px solid #dbe4ed}.grand td{background:#243447;color:#fff;font-weight:bold}.balance td{font-weight:bold;background:#eef6f3}.avoid-break,.items-grid tr{page-break-inside:avoid}
</style></head><body>
<div class="bar"></div><table class="head"><tr><td>@include('invoice-templates.partials.company-branding',['nameClass'=>'brand']){{ $invoice->company->address }}</td><td class="right"><div class="title">{{ $render['is_gst'] ? 'TAX INVOICE' : 'INVOICE' }}</div>{{ $invoice->invoice_number }}</td></tr></table>
<table class="facts"><tr><td width="56%"><strong>Bill to</strong><br>{{ $invoice->billing_company ?: $invoice->billing_name }}<br>{{ $invoice->billing_address }}</td><td><strong>Document date</strong><br>{{ $invoice->issue_date?->format('d M Y') }}<br><strong>Reference</strong><br>{{ $invoice->tax_classification ?: '—' }}</td></tr></table>
@include('invoice-templates.partials.items-grid')
<table class="footer"><tr><td width="56%">@if($render['setting']->gst_presentation==='detailed')@include('invoice-templates.partials.gst-summary')@endif @if($invoice->terms_conditions)<strong>Terms</strong><br>{{ $invoice->terms_conditions }}@endif @include('invoice-templates.partials.payment-qr')</td><td>@include('invoice-templates.partials.totals')</td></tr></table>
</body></html>

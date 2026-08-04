<!doctype html><html><head><style>@page{margin:35px}body{font-family:DejaVu Sans;color:#172033;font-size:11px}.box{border:1px solid #cbd5e1;padding:18px}.right{text-align:right}</style></head><body>
@php($receiptLogo = ($branding['show_logo'] ?? false) ? ($branding['data_uri'] ?? null) : null)
@php($showCompanyName = ($branding['show_company_name'] ?? true) || ! $receiptLogo)
<div class="box"><table width="100%"><tr><td style="text-align:{{ $branding['logo_position'] ?? 'left' }}">
@if($receiptLogo)<img src="{{ $receiptLogo }}" alt="{{ $invoice->company->name }} logo" style="display:inline-block;width:auto;height:auto;max-width:120px;max-height:38px;margin-bottom:6px"><br>
@endif
@if($showCompanyName)<strong>{{ $invoice->company->legal_name ?: $invoice->company->name }}</strong><br>
@endif
<h1>Payment Receipt</h1></td><td class="right"><strong>{{ $payment->receipt_number }}</strong><br>{{ $payment->payment_date?->format('d M Y') }}</td></tr></table><hr><p><strong>Received from:</strong> {{ $invoice->billing_company ?: $invoice->billing_name }}</p><p><strong>Invoice:</strong> {{ $invoice->invoice_number }}</p><p><strong>Amount received:</strong> {{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</p><p><strong>Method:</strong> {{ str($payment->payment_method)->replace('_',' ')->headline() }}</p>@if($payment->transaction_reference)<p><strong>Transaction reference:</strong> {{ $payment->transaction_reference }}</p>@endif<p><strong>Remaining invoice balance:</strong> {{ $invoice->currency }} {{ number_format((float)$invoice->balance_due,2) }}</p></div></body></html>

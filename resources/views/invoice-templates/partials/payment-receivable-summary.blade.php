@php($receivable = $render['receivable'])
<div class="receivable-summary {{ $receivableStyle ?? '' }}">
    <div class="receivable-heading">PAYMENT &amp; RECEIVABLE SUMMARY</div>
    <table role="presentation"><tr>
        <td><small>Invoice date</small><br><strong>{{ $receivable['invoice_date']?->format('d M Y') ?? '—' }}</strong></td>
        <td><small>Due date</small><br><strong>{{ $receivable['due_date']?->format('d M Y') ?? '—' }}</strong></td>
        <td><small>Invoice total</small><br><strong>{{ $invoice->currency }} {{ number_format($receivable['invoice_total'] / 100, 2) }}</strong></td>
        <td><small>Amount received</small><br><strong>{{ $invoice->currency }} {{ number_format($receivable['amount_received'] / 100, 2) }}</strong></td>
        <td class="receivable-balance"><small>Balance receivable</small><br><strong>{{ $invoice->currency }} {{ number_format($receivable['balance_receivable'] / 100, 2) }}</strong></td>
        <td><small>Status</small><br><strong>{{ $receivable['payment_status'] }}</strong>@if($receivable['last_payment_date'])<br><small>Last {{ $receivable['last_payment_date']->format('d M Y') }}</small>@endif</td>
    </tr></table>
</div>

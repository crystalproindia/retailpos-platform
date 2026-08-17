<table class="thermal-totals" role="presentation"><tbody>
    @if((float) $invoice->discount_total !== 0.0)<tr><td>Discount / savings</td><td class="right">-{{ number_format((float) $invoice->discount_total, 2) }}</td></tr>@endif
    @if($render['is_gst'])<tr><td>GST</td><td class="right">{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>@endif
    @if((float) $invoice->adjustment_total !== 0.0)<tr><td>Round-off</td><td class="right">{{ number_format((float) $invoice->adjustment_total, 2) }}</td></tr>@endif
    <tr class="grand"><td>Total</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
    <tr><td>Paid</td><td class="right">{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
    <tr class="balance"><td>Balance due</td><td class="right">{{ number_format((float) $invoice->balance_due, 2) }}</td></tr>
</tbody></table>

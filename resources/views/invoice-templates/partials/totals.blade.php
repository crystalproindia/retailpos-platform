<table class="totals" role="presentation">
    <tbody>
        <tr><td>Taxable value</td><td class="right">{{ number_format((float) $invoice->taxable_total, 2) }}</td></tr>
        @if((float) $invoice->cgst_total !== 0.0)<tr><td>CGST</td><td class="right">{{ number_format((float) $invoice->cgst_total, 2) }}</td></tr>@endif
        @if((float) $invoice->sgst_total !== 0.0)<tr><td>SGST</td><td class="right">{{ number_format((float) $invoice->sgst_total, 2) }}</td></tr>@endif
        @if((float) $invoice->igst_total !== 0.0)<tr><td>IGST</td><td class="right">{{ number_format((float) $invoice->igst_total, 2) }}</td></tr>@endif
        @if((float) $invoice->cess_total !== 0.0)<tr><td>Cess</td><td class="right">{{ number_format((float) $invoice->cess_total, 2) }}</td></tr>@endif
        @if((float) $invoice->adjustment_total !== 0.0)<tr><td>Round-off / adjustment</td><td class="right">{{ number_format((float) $invoice->adjustment_total, 2) }}</td></tr>@endif
        <tr class="grand"><td>Invoice total</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
        <tr><td>Received</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
        @if($render['balance']['available'])
            <tr><td>Previous outstanding</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $render['balance']['previous_outstanding'], 2) }}</td></tr>
        @endif
        <tr class="balance"><td>Current balance</td><td class="right">{{ $invoice->currency }} {{ number_format((float) $render['balance']['current_balance'], 2) }}</td></tr>
    </tbody>
</table>

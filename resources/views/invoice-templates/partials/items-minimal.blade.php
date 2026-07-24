<table class="items-minimal" role="presentation">
    <thead>
        <tr>
            <th>Item</th>
            <th class="right">Qty</th>
            <th class="right">Unit price</th>
            <th class="right">GST</th>
            <th class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong>@if($item->hsn_sac)<br><span class="muted">HSN/SAC {{ $item->hsn_sac }}</span>@endif</td>
                <td class="right">{{ $item->quantity }} {{ $item->unit }}</td>
                <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="right">{{ number_format((float) $item->tax_amount, 2) }}<br><span class="muted">{{ number_format((float) $item->tax_rate, 3) }}%</span></td>
                <td class="right"><strong>{{ number_format((float) $item->line_total, 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

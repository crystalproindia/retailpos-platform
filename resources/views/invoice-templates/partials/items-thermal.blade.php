<table class="thermal-items" role="presentation">
    <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Amount</th></tr></thead>
    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td><strong>{{ str($item->name)->limit($render['template']['paper_format'] === 'thermal_58' ? 26 : 42) }}</strong>@if($render['is_gst'] && $render['setting']->gst_presentation === 'detailed' && $item->hsn_sac)<br><span class="muted">HSN {{ $item->hsn_sac }} · GST {{ number_format((float) $item->tax_rate, 3) }}%</span>@endif</td>
                <td class="right">{{ $item->quantity }}</td><td class="right">{{ number_format((float) $item->unit_price, 2) }}</td><td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

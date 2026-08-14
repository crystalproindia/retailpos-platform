@foreach($render['item_chunks'] as $chunkIndex => $items)
    <table class="items-grid" role="presentation">
        <thead>
            <tr>
                <th>#</th><th>Item description</th>@if($render['is_gst'])<th>HSN/SAC</th>@endif<th class="right">Qty</th><th class="right">Rate</th><th class="right">Taxable</th>@if($render['is_gst'])<th class="right">GST</th>@endif<th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ ($chunkIndex * 50) + $loop->iteration }}</td>
                    <td><strong>{{ $item->name }}</strong>@if($item->description)<br><span class="muted">{{ $item->description }}</span>@endif</td>
                    @if($render['is_gst'])<td>{{ $item->hsn_sac ?: '—' }}</td>@endif<td class="right">{{ $item->quantity }} {{ $item->unit }}</td><td class="right">{{ number_format((float) $item->unit_price, 2) }}</td><td class="right">{{ number_format((float) $item->line_subtotal, 2) }}</td>@if($render['is_gst'])<td class="right">{{ number_format((float) $item->tax_amount, 2) }}<br><span class="muted">{{ number_format((float) $item->tax_rate, 3) }}%</span></td>@endif<td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if(! $loop->last)<div class="item-page-break"></div>@endif
@endforeach

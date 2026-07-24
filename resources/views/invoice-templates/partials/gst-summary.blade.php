<section class="gst-summary avoid-break">
    <h3>GST rate-wise summary</h3>
    <table role="presentation">
        <thead>
            <tr><th>HSN/SAC</th><th class="right">Taxable</th><th class="right">Rate</th><th class="right">CGST</th><th class="right">SGST</th><th class="right">IGST</th><th class="right">Cess</th><th class="right">Total tax</th></tr>
        </thead>
        <tbody>
            @foreach($render['tax_rows'] as $row)
                <tr>
                    <td>{{ $row['hsn_sac'] }}</td>
                    <td class="right">{{ number_format($row['taxable'], 2) }}</td>
                    <td class="right">{{ number_format($row['tax_rate'], 3) }}%</td>
                    <td class="right">{{ $row['cgst'] ? number_format($row['cgst'], 2) : '—' }}</td>
                    <td class="right">{{ $row['sgst'] ? number_format($row['sgst'], 2) : '—' }}</td>
                    <td class="right">{{ $row['igst'] ? number_format($row['igst'], 2) : '—' }}</td>
                    <td class="right">{{ $row['cess'] ? number_format($row['cess'], 2) : '—' }}</td>
                    <td class="right">{{ number_format($row['total_tax'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

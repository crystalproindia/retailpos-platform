@if(($render['retailpos_upi_qr_data_uri'] ?? null) && (($render['setting']->paper_format ?? '') !== 'thermal_58' || ($render['retailpos_upi_qr_force'] ?? false)))
    @php($qrSize = str_starts_with((string) ($render['setting']->paper_format ?? ''), 'thermal_') ? 92 : 82)
    <table class="retailpos-upi-qr avoid-break" role="presentation" style="border:1px solid #dbe3ef;margin-top:8px;padding:5px;page-break-inside:avoid;width:100%"><tr>
        <td width="{{ $qrSize + 12 }}"><img src="{{ $render['retailpos_upi_qr_data_uri'] }}" alt="Scan to Pay UPI QR" width="{{ $qrSize }}" height="{{ $qrSize }}" style="height:{{ $qrSize }}px;width:{{ $qrSize }}px"></td>
        <td style="font-size:8px;vertical-align:middle"><strong>Scan to Pay</strong><br><span>UPI: {{ \App\Services\Crm\InvoicePaymentQrService::RETAILPOS_UPI_ID }}</span></td>
    </tr></table>
@endif

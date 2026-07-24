@if($render['payment_qr_data_uri'])
    <div class="payment-qr avoid-break">
        <img src="{{ $render['payment_qr_data_uri'] }}" alt="Payment QR" width="96" height="96">
        <div><strong>Scan to pay</strong><br><span>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }} outstanding</span></div>
    </div>
@endif

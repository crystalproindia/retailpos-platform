@php($details = $render['payment_details'] ?? null)
@php($paperFormat = $render['setting']->paper_format ?? 'a4')
@if($details)
    <section class="payment-details avoid-break">
        <strong>Payment details</strong>
        @if($paperFormat === 'thermal_58')
            @if(data_get($details, 'upi_id'))<div>UPI: {{ $details['upi_id'] }}</div>
            @elseif(data_get($details, 'payment_url'))<div>Payment link: {{ $details['payment_url'] }}</div>
            @elseif(data_get($details, 'account_number'))<div>A/C: {{ $details['account_number'] }}</div>@endif
        @else
            <table role="presentation">
                @if(data_get($details, 'account_holder_name'))<tr><td>Account holder</td><td>{{ $details['account_holder_name'] }}</td></tr>@endif
                @if(data_get($details, 'bank_name'))<tr><td>Bank</td><td>{{ $details['bank_name'] }}</td></tr>@endif
                @if(data_get($details, 'account_number'))<tr><td>Account number</td><td>{{ $details['account_number'] }}</td></tr>@endif
                @if(data_get($details, 'ifsc_code'))<tr><td>IFSC</td><td>{{ $details['ifsc_code'] }}</td></tr>@endif
                @if(data_get($details, 'branch_name'))<tr><td>Branch</td><td>{{ $details['branch_name'] }}</td></tr>@endif
                @if(data_get($details, 'swift_bic'))<tr><td>SWIFT / BIC</td><td>{{ $details['swift_bic'] }}</td></tr>@endif
                @if(data_get($details, 'upi_id'))<tr><td>UPI</td><td>{{ $details['upi_id'] }}</td></tr>@endif
                @if(data_get($details, 'payment_url'))<tr><td>Payment link</td><td class="payment-details-value">{{ $details['payment_url'] }}</td></tr>@endif
            </table>
        @endif
        @if(data_get($details, 'payment_note'))<p>{{ $details['payment_note'] }}</p>@endif
    </section>
@endif

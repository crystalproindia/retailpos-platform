@if($presentation['payment_details'] ?? null)
    <section class="relative z-10 mt-8 border-t border-slate-200 pt-6">
        <h2 class="font-semibold">Payment details</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            @foreach(['account_holder_name' => 'Account holder', 'bank_name' => 'Bank', 'account_number' => 'Account number', 'ifsc_code' => 'IFSC', 'branch_name' => 'Branch', 'swift_bic' => 'SWIFT / BIC', 'upi_id' => 'UPI', 'payment_url' => 'Payment link'] as $field => $label)
                @if(data_get($presentation, 'payment_details.'.$field))<div class="min-w-0"><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-slate-800">{{ $presentation['payment_details'][$field] }}</dd></div>@endif
            @endforeach
        </dl>
        @if(data_get($presentation, 'payment_details.payment_note'))<p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $presentation['payment_details']['payment_note'] }}</p>@endif
    </section>
@endif

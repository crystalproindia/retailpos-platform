@if($presentation['payment_details'] ?? null)
    <section class="relative z-10 mt-8 border-t border-slate-200 pt-6">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <h2 class="font-semibold text-slate-900">Payment details</h2>
        <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            @foreach(['account_holder_name' => 'Account holder', 'bank_name' => 'Bank', 'account_number' => 'Account number', 'ifsc_code' => 'IFSC', 'branch_name' => 'Branch', 'swift_bic' => 'SWIFT / BIC', 'upi_id' => 'UPI', 'payment_url' => 'Payment link'] as $field => $label)
                @if(data_get($presentation, 'payment_details.'.$field))<div class="min-w-0"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium leading-6 text-slate-800">{{ $presentation['payment_details'][$field] }}</dd></div>@endif
            @endforeach
        </dl>
        @if(data_get($presentation, 'payment_details.payment_note'))<p class="mt-4 whitespace-pre-line border-t border-slate-200 pt-4 text-sm leading-6 text-slate-600">{{ $presentation['payment_details']['payment_note'] }}</p>@endif
        </div>
    </section>
@endif

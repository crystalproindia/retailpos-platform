@php($transactions = $paymentHistory['transactions'] ?? collect())
@php($showMethod = $transactions->contains(fn ($payment) => filled($payment['method'])))
@php($showReference = $transactions->contains(fn ($payment) => filled($payment['reference'])))
@php($showNotes = $transactions->contains(fn ($payment) => filled($payment['notes'])))
@php($compactHistory = str_starts_with((string) ($paperFormat ?? ''), 'thermal_'))
@php($minimalHistory = ($paperFormat ?? null) === 'thermal_58')
<section class="invoice-payment-history {{ $compactHistory ? 'is-thermal' : '' }} {{ $minimalHistory ? 'is-thermal-58' : '' }}" aria-label="Payments received">
    <h2>PAYMENTS RECEIVED</h2>
    @if($transactions->isEmpty())
        <p class="payment-history-empty">No payments received yet.</p>
    @else
        <table>
            <thead><tr><th>Date</th>@if(! $minimalHistory && $showMethod)<th>Method</th>@endif @if(! $minimalHistory && $showReference)<th>Reference</th>@endif @if(! $compactHistory && $showNotes)<th>Notes / Remarks</th>@endif<th class="payment-history-amount">Amount Received</th></tr></thead>
            <tbody>
                @foreach($transactions as $payment)
                    <tr><td>{{ $payment['date']?->format('d M Y') ?? '—' }}</td>@if(! $minimalHistory && $showMethod)<td>{{ $payment['method'] }}</td>@endif @if(! $minimalHistory && $showReference)<td class="payment-history-wrap">{{ $payment['reference'] }}@if(! empty($publicToken))<br><a href="{{ route('invoices.public.receipts.pdf', [$publicToken, $payment['payment_id']]) }}">Receipt PDF</a>@endif</td>@endif @if(! $compactHistory && $showNotes)<td class="payment-history-wrap">{{ $payment['notes'] }}</td>@endif<td class="payment-history-amount">{{ $invoice->currency }} {{ number_format($payment['amount_minor'] / 100, 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="{{ 1 + ((! $minimalHistory && $showMethod) ? 1 : 0) + ((! $minimalHistory && $showReference) ? 1 : 0) + ((! $compactHistory && $showNotes) ? 1 : 0) }}">Total Received</td><td class="payment-history-amount">{{ $invoice->currency }} {{ number_format($paymentHistory['total_minor'] / 100, 2) }}</td></tr></tfoot>
        </table>
    @endif
    @if(! $paymentHistory['reconciles_summary'])
        <p class="payment-history-reconciliation">Recorded payment transactions total {{ $invoice->currency }} {{ number_format($paymentHistory['total_minor'] / 100, 2) }}; the invoice summary shows {{ $invoice->currency }} {{ number_format($paymentHistory['summary_minor'] / 100, 2) }} received.</p>
    @endif
</section>

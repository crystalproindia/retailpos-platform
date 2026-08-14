@if($render['is_gst'] && $render['setting']->gst_presentation === 'detailed')
    <section class="thermal-gst"><strong>GST summary</strong>@foreach($render['tax_rows'] as $row)<div>{{ $row['hsn_sac'] }} · {{ number_format($row['tax_rate'], 3) }}%<span>{{ number_format($row['total_tax'], 2) }}</span></div>@endforeach</section>
@elseif($render['is_gst'])
    <p class="thermal-gst-summary">GST included: {{ number_format((float) $invoice->tax_total, 2) }}</p>
@endif

@if($invoice->show_authorized_signature && ($render['signature']['data_uri'] || $render['signature']['name']))
    <div class="authorized-signature avoid-break" style="margin-top:14px;text-align:right">
        <strong>For {{ $invoice->company->legal_name ?: $invoice->company->name }}</strong><br>
        @if($render['signature']['data_uri'])<img src="{{ $render['signature']['data_uri'] }}" alt="Authorized signature" style="display:inline-block;max-width:130px;max-height:52px;object-fit:contain;margin:5px 0">@else<br><br>@endif
        @if($render['signature']['name'])<strong>{{ $render['signature']['name'] }}</strong><br>@endif
        {{ $render['signature']['designation'] ?: 'Authorized Signatory' }}
    </div>
@endif

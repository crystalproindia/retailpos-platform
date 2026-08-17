@if($invoice->show_authorized_signature && ($render['signature']['data_uri'] || $render['signature']['name']))
    @php($isThermal = str_starts_with((string) ($render['setting']->paper_format ?? ''), 'thermal_'))
    @php($imageHeight = $isThermal ? 38 : 58)
    @php($imageWidth = $isThermal ? 90 : 130)
    <table class="authorized-signature avoid-break" role="presentation" style="border-collapse:collapse;margin-top:{{ $isThermal ? 8 : 18 }}px;page-break-inside:avoid;width:100%">
        <tr>
            <td align="center" style="padding:0;text-align:center;vertical-align:top">
                <div style="font-weight:bold;margin-bottom:3px">For {{ $invoice->company->legal_name ?: $invoice->company->name }}</div>
                <div style="height:{{ $imageHeight }}px;text-align:center">
                    @if($render['signature']['data_uri'])<img src="{{ $render['signature']['data_uri'] }}" alt="Authorized signature" style="display:block;margin:0 auto;max-height:{{ $imageHeight - 4 }}px;max-width:{{ $imageWidth }}px">@endif
                </div>
                @if($render['signature']['name'])<div style="font-weight:bold;line-height:1.35">{{ $render['signature']['name'] }}</div>@endif
                <div style="line-height:1.35">{{ $render['signature']['designation'] ?: 'Authorized Signatory' }}</div>
            </td>
        </tr>
    </table>
@endif

@php
    $branding = $render['branding'] ?? [];
    $position = in_array($branding['logo_position'] ?? null, ['left', 'center', 'right'], true) ? $branding['logo_position'] : 'left';
    $size = in_array($branding['logo_size'] ?? null, ['small', 'medium', 'large'], true) ? $branding['logo_size'] : 'medium';
    $dimensions = ['small' => ['width' => 90, 'height' => 24], 'medium' => ['width' => 140, 'height' => 36], 'large' => ['width' => 180, 'height' => 48]][$size];
    $logo = ($branding['show_logo'] ?? false) ? ($branding['data_uri'] ?? null) : null;
    $showName = ($branding['show_company_name'] ?? true) || ! $logo;
@endphp
<div style="text-align: {{ $position }};">
    @if($logo)
        <img src="{{ $logo }}" alt="{{ $invoice->company->legal_name ?: $invoice->company->name }} logo" style="display:inline-block; width:auto; height:auto; max-width:{{ $dimensions['width'] }}px; max-height:{{ $dimensions['height'] }}px; margin:0 0 5px;" />
    @endif
    @if($showName)
        <div class="{{ $nameClass ?? 'company-name' }}">{{ $invoice->company->legal_name ?: $invoice->company->name }}</div>
    @endif
</div>

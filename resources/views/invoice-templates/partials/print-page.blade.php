@php
    $paperFormat = $render['setting']->paper_format ?? 'a4';
    $orientation = $render['setting']->orientation ?? 'portrait';
    $pageSize = match ($paperFormat) {
        'a5' => $orientation === 'landscape' ? '210mm 148mm' : '148mm 210mm',
        'thermal_80' => '80mm auto',
        'thermal_58' => '58mm auto',
        default => $orientation === 'landscape' ? '297mm 210mm' : '210mm 297mm',
    };
@endphp
@page { size: {{ $pageSize }}; margin: {{ str_starts_with($paperFormat, 'thermal_') ? '3mm' : '12mm' }}; }

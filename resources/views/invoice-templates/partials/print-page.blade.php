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
.invoice-watermark { height: 38%; left: 15%; opacity: .12; pointer-events: none; position: fixed; text-align: center; top: 31%; width: 70%; z-index: 0; }
.invoice-watermark img { height: 100%; max-width: 100%; object-fit: contain; width: auto; }
.invoice-watermark ~ * { position: relative; z-index: 1; }
.payment-details { border-top: 1px solid #cbd5e1; font-size: {{ str_starts_with($paperFormat, 'thermal_') ? '7px' : '8px' }}; line-height: 1.4; margin-top: {{ str_starts_with($paperFormat, 'thermal_') ? '7px' : '12px' }}; padding-top: {{ str_starts_with($paperFormat, 'thermal_') ? '5px' : '8px' }}; page-break-inside: avoid; }
.payment-details table { margin-top: 4px; table-layout: fixed; width: 100%; }
.payment-details td { border: 0; padding: 1px 5px 1px 0; vertical-align: top; }
.payment-details td:first-child { color: #64748b; width: 28%; }
.payment-details-value { overflow-wrap: anywhere; word-break: break-word; }
.payment-details p { margin: 4px 0 0; }

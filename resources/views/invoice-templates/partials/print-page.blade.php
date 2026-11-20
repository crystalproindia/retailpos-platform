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
.invoice-payment-history { border: 1px solid #cbd5e1; margin-top: 10px; padding: 7px 9px; page-break-inside: avoid; font-size: 8px; line-height: 1.35; }
.invoice-payment-history h2 { color: #0f172a; font-size: 9px; font-weight: bold; margin: 0 0 5px; }
.invoice-payment-history table { border-collapse: collapse; table-layout: fixed; width: 100%; }
.invoice-payment-history th, .invoice-payment-history td { border-bottom: 1px solid #e2e8f0; padding: 3px 4px; text-align: left; vertical-align: top; }
.invoice-payment-history th { background: #f8fafc; color: #475569; font-size: 7px; text-transform: uppercase; }
.invoice-payment-history tfoot td { border-bottom: 0; font-weight: bold; padding-top: 5px; }
.invoice-payment-history .payment-history-amount { text-align: right; white-space: nowrap; }
.invoice-payment-history .payment-history-wrap { overflow-wrap: anywhere; word-break: break-word; }
.invoice-payment-history .payment-history-empty, .invoice-payment-history .payment-history-reconciliation { color: #475569; margin: 3px 0 0; }
.invoice-payment-history.is-thermal { font-size: 7px; margin-top: 6px; padding: 4px; }
.invoice-payment-history.is-thermal h2 { font-size: 7px; margin-bottom: 3px; }
.invoice-payment-history.is-thermal th, .invoice-payment-history.is-thermal td { padding: 2px; }
.invoice-payment-history.is-thermal-58 .payment-history-reconciliation { font-size: 6px; }

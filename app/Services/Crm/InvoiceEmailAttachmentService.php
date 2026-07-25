<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\NotificationDelivery;
use RuntimeException;

class InvoiceEmailAttachmentService
{
    public const TYPE = 'sales_invoice_pdf';

    public function __construct(private readonly InvoicePdfService $pdf) {}

    /** @return array{contents: string, filename: string, mime: string}|null */
    public function forDelivery(NotificationDelivery $delivery): ?array
    {
        if (($delivery->payload['attachment_type'] ?? null) !== self::TYPE) {
            return null;
        }

        if ($delivery->related_type !== (new CrmInvoice)->getMorphClass() || ! $delivery->related_id) {
            throw new RuntimeException('Invoice PDF attachment could not be generated.');
        }

        $invoice = CrmInvoice::query()
            ->where('company_id', $delivery->company_id)
            ->with(['company', 'items'])
            ->find($delivery->related_id);

        if (! $invoice) {
            throw new RuntimeException('Invoice PDF attachment could not be generated.');
        }

        return $this->pdf->attachment($invoice);
    }
}

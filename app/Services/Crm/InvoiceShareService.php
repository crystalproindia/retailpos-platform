<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Validation\ValidationException;

class InvoiceShareService
{
    public function __construct(private readonly PublicInvoiceService $links, private readonly EmailDeliveryService $email, private readonly AuditLogger $audit) {}

    /** @return array{url:string,message:string,phone:?string,whatsapp_url:?string} */
    public function whatsapp(CrmInvoice $invoice, User $user): array
    {
        $link = $this->links->issue($invoice, $user);
        $phone = $this->phone($invoice->billing_phone);
        $message = "Hello ".($invoice->billing_name ?: $invoice->billing_company ?: 'there').",\n\nYour RetailPOS invoice {$invoice->invoice_number} is ready.\n\nAmount: {$invoice->currency} ".number_format((float) $invoice->grand_total, 2)."\nBalance due: {$invoice->currency} ".number_format((float) $invoice->balance_due, 2)."\nDue date: ".($invoice->due_date?->format('d M Y') ?: 'Not specified')."\n\nView invoice:\n{$link->url}\n\nRegards,\nRetailPOS.biz";
        $this->audit->record('crm.invoice.whatsapp_prepared', $invoice, 'Invoice WhatsApp share prepared', ['company_id' => $invoice->company_id, 'has_recipient' => $phone !== null]);
        return ['url' => $link->url, 'message' => $message, 'phone' => $phone, 'whatsapp_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null];
    }

    /** @return array{configured:bool,queued:bool} */
    public function send(CrmInvoice $invoice, User $user, string $recipient, string $template = 'invoice_issued', ?string $message = null): array
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => 'Issue the invoice before sending it to a customer.']);
        }

        $link = $this->links->issue($invoice, $user);
        $delivery = $this->email->queue($invoice->company_id, $recipient, 'RetailPOS Invoice - '.$invoice->invoice_number, $template, [
            'heading' => 'Your RetailPOS invoice is ready', 'greeting' => 'Hello '.($invoice->billing_name ?: $invoice->billing_company ?: 'there').',',
            'message' => $message ?: 'Please review your invoice and payment balance.',
            'details' => ['Invoice' => $invoice->invoice_number, 'Total' => $invoice->currency.' '.number_format((float) $invoice->grand_total, 2), 'Balance due' => $invoice->currency.' '.number_format((float) $invoice->balance_due, 2), 'Due date' => $invoice->due_date?->format('d M Y') ?: 'Not specified'],
            'action_url' => $link->url, 'action_label' => 'View invoice',
        ], $invoice, $user, 'invoice-email:'.$invoice->id.':'.$template.':'.hash('sha256', strtolower($recipient).'|'.($message ?? '')));
        if ($delivery->status !== 'skipped_not_configured' && in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true)) { $invoice->update(['status' => InvoiceStatus::Sent, 'sent_at' => now(), 'updated_by' => $user->id]); }
        $this->audit->record('crm.invoice.email_queued', $invoice, 'Invoice email queued', ['company_id' => $invoice->company_id, 'delivery_id' => $delivery->id, 'template' => $template]);
        return ['configured' => $delivery->status !== 'skipped_not_configured', 'queued' => $delivery->status === 'queued'];
    }

    /** @return array{configured:bool,queued:bool} */
    public function sendReceipt(CrmInvoicePayment $payment, User $user): array
    {
        $invoice = $payment->invoice;
        return $this->send($invoice, $user, (string) $invoice->billing_email, 'payment_receipt', 'We received your payment of '.$invoice->currency.' '.number_format((float) $payment->amount, 2).' against invoice '.$invoice->invoice_number.'. Receipt '.$payment->receipt_number.' is available from your invoice page.');
    }

    /** @return array{configured:bool,queued:bool} */
    public function remind(CrmInvoice $invoice, User $user, string $recipient): array
    {
        $result = $this->send($invoice, $user, $recipient, $invoice->isOverdue() ? 'invoice_overdue' : 'invoice_reminder', 'This is a friendly reminder that your invoice balance is '.$invoice->currency.' '.number_format((float) $invoice->balance_due, 2).'.');
        $this->audit->record('crm.invoice.reminder_queued', $invoice, 'Invoice reminder queued', [
            'company_id' => $invoice->company_id,
        ]);

        return $result;
    }

    /** @return array{message:string,whatsapp_url:?string} */
    public function receiptWhatsapp(CrmInvoicePayment $payment, User $user): array
    {
        $invoice = $payment->invoice;
        $link = $this->links->issue($invoice, $user);
        $phone = $this->phone($invoice->billing_phone);
        $message = "Hello ".($invoice->billing_name ?: $invoice->billing_company ?: 'there').",\n\nWe received your payment of {$payment->currency} ".number_format((float) $payment->amount, 2)." against invoice {$invoice->invoice_number}.\n\nReceipt:\n{$link->url}/receipts/{$payment->id}\n\nThank you,\nRetailPOS.biz";
        $this->audit->record('crm.invoice.receipt_whatsapp_prepared', $payment, 'Receipt WhatsApp share prepared', [
            'company_id' => $invoice->company_id,
            'has_recipient' => $phone !== null,
        ]);

        return ['message' => $message, 'whatsapp_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null];
    }

    private function phone(?string $value): ?string { $digits = preg_replace('/\D+/', '', (string) $value); if (! $digits) return null; if (str_starts_with($digits, '00')) $digits = substr($digits, 2); if (strlen($digits) === 10) $digits = '91'.$digits; return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null; }
}

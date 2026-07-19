<?php
namespace App\Support\Crm;
use App\Models\Crm\CrmInvoice;
final readonly class PublicInvoiceLink { public function __construct(public CrmInvoice $invoice, public string $url) {} }

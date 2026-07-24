<?php
namespace App\Http\Controllers;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\PublicInvoiceService;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class PublicInvoiceController extends Controller { public function show(PublicInvoiceService $invoices,InvoiceTemplateService $templates,string $token):Response{$invoice=$invoices->view($invoices->find($token));$render=$templates->renderData($invoice->loadMissing(['company','items'])); return response()->view('public.invoice',compact('invoice','token','render'))->header('X-Robots-Tag','noindex, nofollow');} public function pdf(PublicInvoiceService $invoices,InvoicePdfService $pdf,string $token):Response{$invoice=$invoices->find($token);return $pdf->document($invoice)->download($pdf->filename($invoice));} public function receipt(PublicInvoiceService $invoices,InvoicePdfService $pdf,string $token,int $payment):Response{$invoice=$invoices->find($token);$record=$invoice->payments()->findOrFail($payment);return $pdf->receipt($invoice,$record)->download($pdf->receiptFilename($record));} }

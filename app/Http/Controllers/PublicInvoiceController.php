<?php
namespace App\Http\Controllers;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class PublicInvoiceController extends Controller { public function show(PublicInvoiceService $invoices,string $token):Response{$invoice=$invoices->view($invoices->find($token)); return response()->view('public.invoice',compact('invoice','token'))->header('X-Robots-Tag','noindex, nofollow');} public function pdf(PublicInvoiceService $invoices,InvoicePdfService $pdf,string $token):Response{$invoice=$invoices->find($token);return $pdf->document($invoice)->download($pdf->filename($invoice));} public function receipt(PublicInvoiceService $invoices,InvoicePdfService $pdf,string $token,int $payment):Response{$invoice=$invoices->find($token);$record=$invoice->payments()->findOrFail($payment);return $pdf->receipt($invoice,$record)->download($pdf->receiptFilename($record));} }

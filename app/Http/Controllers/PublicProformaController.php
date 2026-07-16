<?php
namespace App\Http\Controllers; use App\Models\Crm\CrmProformaInvoice; use Illuminate\View\View; class PublicProformaController extends Controller { public function show(string $token):View{$proforma=CrmProformaInvoice::where('public_token',$token)->with('items')->firstOrFail();return view('public.proforma',compact('proforma'));} }

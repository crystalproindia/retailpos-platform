<?php
namespace App\Http\Requests\Crm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreInvoicePaymentRequest extends FormRequest { public function authorize():bool{return (bool)$this->user()?->can('sales.payments.record');} public function rules():array{return ['amount'=>['required','numeric','gt:0'],'currency'=>['required','string','size:3'],'payment_date'=>['required','date'],'payment_method'=>['required',Rule::in(['bank_transfer','cash','cheque','card','upi','online','other'])],'transaction_reference'=>['nullable','string','max:160'],'bank_name'=>['nullable','string','max:160'],'cheque_number'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:3000'],'status'=>['nullable',Rule::in(['recorded','cleared','pending'])]];} }

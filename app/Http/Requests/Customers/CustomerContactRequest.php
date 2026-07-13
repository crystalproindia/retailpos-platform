<?php
namespace App\Http\Requests\Customers; use Illuminate\Foundation\Http\FormRequest;
class CustomerContactRequest extends FormRequest {public function authorize():bool{return true;} public function rules():array{return ['name'=>['required','string','max:255'],'designation'=>['nullable','string','max:255'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'whatsapp'=>['nullable','string','max:50'],'is_primary'=>['nullable','boolean'],'notes'=>['nullable','string'],'is_active'=>['nullable','boolean']];}}

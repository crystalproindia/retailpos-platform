<?php
namespace App\Http\Requests\Customers; use Illuminate\Foundation\Http\FormRequest;
class CustomerGroupRequest extends FormRequest {public function authorize():bool{return true;} public function rules():array{return ['name'=>['required','string','max:255'],'slug'=>['nullable','string','max:255'],'description'=>['nullable','string'],'discount_percentage'=>['nullable','numeric','min:0','max:100'],'loyalty_multiplier'=>['nullable','numeric','min:0'],'is_default'=>['nullable','boolean'],'is_active'=>['nullable','boolean'],'sort_order'=>['nullable','integer','min:0']];}}

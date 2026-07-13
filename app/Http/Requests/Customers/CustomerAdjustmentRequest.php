<?php
namespace App\Http\Requests\Customers; use Illuminate\Foundation\Http\FormRequest;
class CustomerAdjustmentRequest extends FormRequest {public function authorize():bool{return true;} public function rules():array{return ['amount'=>['required','numeric','not_in:0'],'description'=>['required','string','max:1000']];}}

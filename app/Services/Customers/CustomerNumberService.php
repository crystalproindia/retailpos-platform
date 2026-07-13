<?php
namespace App\Services\Customers; use App\Models\Customers\CustomerSetting; use Illuminate\Support\Facades\DB;
class CustomerNumberService { public function next(int $companyId):string{return DB::transaction(function()use($companyId){$settings=CustomerSetting::query()->lockForUpdate()->firstOrCreate(['company_id'=>$companyId]);$number=$settings->customer_number_prefix.'-'.str_pad((string)$settings->next_customer_number,6,'0',STR_PAD_LEFT);$settings->increment('next_customer_number');return $number;});} }

<?php
namespace App\Models\Customers; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['company_id','customer_id','type','name','phone','address_line_1','address_line_2','city','state','country','postal_code','is_default'])] class CustomerAddress extends Model {use SoftDeletes; protected function casts():array{return ['is_default'=>'boolean'];} public function customer():BelongsTo{return $this->belongsTo(Customer::class);}}

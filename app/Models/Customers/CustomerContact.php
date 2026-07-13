<?php
namespace App\Models\Customers; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['company_id','customer_id','name','designation','email','phone','whatsapp','is_primary','notes','is_active'])] class CustomerContact extends Model {use SoftDeletes; protected function casts():array{return ['is_primary'=>'boolean','is_active'=>'boolean'];} public function customer():BelongsTo{return $this->belongsTo(Customer::class);}}

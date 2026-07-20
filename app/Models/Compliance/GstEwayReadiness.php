<?php
namespace App\Models\Compliance;
use App\Models\User; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['company_id','document_type','document_model','document_id','status','document_date','transport_mode','transport_distance','transporter_id','transporter_name','vehicle_number','vehicle_type','dispatch_from','ship_to','reason_for_transport','provider_reference','safe_error_message','validated_at','validated_by'])]
class GstEwayReadiness extends Model { protected function casts():array{return ['document_date'=>'date','validated_at'=>'datetime'];} public function validator():BelongsTo{return $this->belongsTo(User::class,'validated_by');} }

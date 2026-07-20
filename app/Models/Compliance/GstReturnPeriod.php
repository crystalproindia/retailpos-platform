<?php
namespace App\Models\Compliance;
use App\Models\User; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['company_id','period','financial_year','status','reviewed_by','reviewed_at','locked_by','locked_at','reopen_reason'])]
class GstReturnPeriod extends Model { protected function casts():array{return ['reviewed_at'=>'datetime','locked_at'=>'datetime'];} public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by');} }

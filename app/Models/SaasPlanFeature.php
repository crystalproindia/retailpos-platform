<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SaasPlanFeature extends Model {protected $guarded=[]; protected function casts():array{return ['is_enabled'=>'boolean'];} public function plan():BelongsTo{return $this->belongsTo(SaasPlan::class,'saas_plan_id');}}

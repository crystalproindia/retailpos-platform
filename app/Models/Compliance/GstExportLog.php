<?php
namespace App\Models\Compliance;
use App\Models\User; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['company_id','export_type','period','format','draft_export','record_count','validation_summary','generated_by'])]
class GstExportLog extends Model { protected function casts():array{return ['draft_export'=>'boolean','validation_summary'=>'array'];} public function generator():BelongsTo{return $this->belongsTo(User::class,'generated_by');} }

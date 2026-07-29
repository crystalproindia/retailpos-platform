<?php
namespace App\Models\Compliance;
use App\Models\Branch; use Illuminate\Database\Eloquent\Attributes\Fillable; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['company_id','branch_id','document_type','financial_year','prefix','last_sequence','is_active'])]
class GstDocumentSeries extends Model { protected function casts():array{return ['is_active'=>'boolean'];} public function branch():BelongsTo{return $this->belongsTo(Branch::class);} }

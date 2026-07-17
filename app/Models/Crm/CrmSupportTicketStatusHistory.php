<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'old_status', 'new_status', 'changed_by', 'note'])]
class CrmSupportTicketStatusHistory extends Model
{
    public const UPDATED_AT = null;
    public function ticket(): BelongsTo { return $this->belongsTo(CrmSupportTicket::class, 'ticket_id'); }
    public function changer(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}

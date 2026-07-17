<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'message_id', 'title', 'file_path', 'external_url', 'mime_type', 'file_size', 'uploaded_by'])]
class CrmSupportTicketAttachment extends Model
{
    public function ticket(): BelongsTo { return $this->belongsTo(CrmSupportTicket::class, 'ticket_id'); }
    public function message(): BelongsTo { return $this->belongsTo(CrmSupportTicketMessage::class, 'message_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}

<?php

namespace App\Models\Crm;

use App\Enums\Crm\SupportTicketMessageVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'message', 'visibility', 'message_type', 'created_by', 'customer_portal_user_id'])]
class CrmSupportTicketMessage extends Model
{
    protected function casts(): array { return ['visibility' => SupportTicketMessageVisibility::class]; }
    public function ticket(): BelongsTo { return $this->belongsTo(CrmSupportTicket::class, 'ticket_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function portalUser(): BelongsTo { return $this->belongsTo(CrmCustomerPortalUser::class, 'customer_portal_user_id'); }
}

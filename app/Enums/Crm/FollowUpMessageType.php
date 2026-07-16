<?php

namespace App\Enums\Crm;

enum FollowUpMessageType: string
{
    case WhatsAppFollowUp = 'whatsapp_follow_up';
    case EmailFollowUp = 'email_follow_up';
    case CallScript = 'call_script';
    case DemoReminder = 'demo_reminder';
    case ProposalFollowUp = 'proposal_follow_up';
    case ProformaPaymentReminder = 'proforma_payment_reminder';
    case ColdLeadReactivation = 'cold_lead_reactivation';
    case LostLeadRevival = 'lost_lead_revival';
    case PaymentThankYou = 'payment_thank_you';
    case MeetingConfirmation = 'meeting_confirmation';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}

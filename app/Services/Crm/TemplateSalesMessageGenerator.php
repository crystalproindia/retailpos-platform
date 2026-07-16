<?php

namespace App\Services\Crm;

use App\Contracts\Crm\SalesMessageGeneratorInterface;
use App\Data\Crm\CrmLeadScoreResult;
use App\Data\Crm\GeneratedSalesMessage;
use App\Enums\Crm\FollowUpMessageType;
use App\Models\Crm\CrmLead;

class TemplateSalesMessageGenerator implements SalesMessageGeneratorInterface
{
    /** @param array{message_type?: string, tone?: string, length?: string} $options */
    public function generate(CrmLead $lead, CrmLeadScoreResult $score, array $options = []): GeneratedSalesMessage
    {
        $type = FollowUpMessageType::tryFrom($options['message_type'] ?? '') ?? FollowUpMessageType::FollowUp;
        $name = $lead->contact_name ?: $lead->business_name ?: 'there';
        $company = $lead->business_name ?: 'your team';
        $quotation = $lead->latestQuotation;
        $proforma = $lead->latestProforma;
        $demo = $lead->latestDemoSchedule;
        $proposalReference = $quotation?->quotation_number ? " ({$quotation->quotation_number})" : '';
        $paymentSummary = $proforma ? "\nTotal: {$proforma->currency} ".number_format((float) $proforma->grand_total, 2)."\nPaid: {$proforma->currency} ".number_format((float) $proforma->paid_amount, 2)."\nBalance: {$proforma->currency} ".number_format((float) $proforma->balance_amount, 2) : '';
        $link = $type === FollowUpMessageType::ProposalFollowUp ? $quotation?->public_url : ($type === FollowUpMessageType::ProformaPaymentReminder ? $proforma?->public_url : null);
        $message = match ($type) {
            FollowUpMessageType::MeetingConfirmation => "Hi {$name}, your RetailPOS demo is confirmed".($demo?->starts_at ? ' for '.$demo->starts_at->setTimezone($demo->timezone)->format('d M, h:i A') : '').". We will focus on the workflow most relevant to {$company}. Please reply with any questions you want us to cover.",
            FollowUpMessageType::DemoReminder => "Hi {$name}, a quick reminder about your RetailPOS demo".($demo?->starts_at ? ' on '.$demo->starts_at->setTimezone($demo->timezone)->format('d M, h:i A') : '').". Please reply if the timing needs to change, otherwise we will be ready to focus on your priorities.",
            FollowUpMessageType::ProposalFollowUp => "Hi {$name}, I wanted to check whether the RetailPOS proposal{$proposalReference} raised any questions for {$company}. We can clarify scope, rollout, or commercial details.",
            FollowUpMessageType::ProformaPaymentReminder => "Hi {$name}, this is a gentle reminder regarding your RetailPOS proforma invoice ".($proforma?->proforma_number ?? '').'.'.$paymentSummary."\nCould you confirm the expected payment date or any blocker we can help resolve?",
            FollowUpMessageType::ColdLeadReactivation, FollowUpMessageType::LostLeadRevival => "Hi {$name}, we are checking whether RetailPOS is still relevant for {$company}. If the timing has changed, a quick reply is enough and we will update the plan.",
            FollowUpMessageType::PaymentThankYou => "Hi {$name}, thank you for your payment towards RetailPOS. Our team will keep the next rollout step clear and coordinated.",
            FollowUpMessageType::CallScript => "Hi {$name}, I am calling about your RetailPOS enquiry for {$company}. {$score->nextBestAction}",
            default => "Hi {$name}, I am following up on your RetailPOS enquiry for {$company}. {$score->nextBestAction}",
        };
        if ($link) $message .= "\n\nYou can view the document here: {$link}";
        if (($options['tone'] ?? 'professional') === 'friendly') $message = str_replace('Could you', 'Could you please', $message);
        if (($options['tone'] ?? 'professional') === 'premium') $message = str_replace('Hi ', 'Hello ', $message);
        if (($options['tone'] ?? 'professional') === 'tamil_english') $message = str_replace('Hi ', 'Vanakkam ', $message);
        $message .= "\n\nRegards,\nRetailPOS.biz\nPowered by CrystalPro";
        if (($options['length'] ?? 'normal') === 'short') $message = preg_replace('/\n\nRegards,.*/s', "\n\nRetailPOS.biz", $message) ?? $message;

        $subject = match ($type) {
            FollowUpMessageType::ProposalFollowUp => 'RetailPOS proposal follow-up',
            FollowUpMessageType::ProformaPaymentReminder => 'RetailPOS payment follow-up',
            FollowUpMessageType::MeetingConfirmation, FollowUpMessageType::DemoReminder => 'RetailPOS demo update',
            default => 'RetailPOS follow-up',
        };
        $phone = preg_replace('/\D+/', '', (string) $lead->phone);

        return new GeneratedSalesMessage(
            subject: $subject,
            message: $message,
            whatsAppUrl: $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
            emailUrl: $lead->email ? 'mailto:'.rawurlencode($lead->email).'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        );
    }
}

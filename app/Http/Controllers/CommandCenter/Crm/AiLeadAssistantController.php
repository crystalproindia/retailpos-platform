<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\GenerateAiFollowUpRequest;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\CrmFollowUpAssistantService;
use App\Services\Crm\CrmLeadScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiLeadAssistantController extends Controller
{
    public function analyze(Request $request, LeadRepository $leads, CrmLeadScoringService $scoring, int $lead): RedirectResponse
    {
        abort_unless($request->user()->can('crm.ai.refresh_score'), 403);
        $result = $scoring->refresh($leads->findForUser($request->user(), $lead), $request->user(), true);

        return back()->with('status', "AI lead score refreshed: {$result->score}/100.");
    }

    public function generate(GenerateAiFollowUpRequest $request, LeadRepository $leads, CrmLeadScoringService $scoring, CrmFollowUpAssistantService $assistant, int $lead): RedirectResponse
    {
        $lead = $leads->findForUser($request->user(), $lead);
        $score = $scoring->refresh($lead, $request->user());
        $message = $assistant->generate($lead, $request->user(), $score, $request->validated());

        return back()->with('aiFollowUp', [
            'subject' => $message->subject,
            'message' => $message->message,
            'whatsapp_url' => $message->whatsAppUrl,
            'email_url' => $message->emailUrl,
            'options' => $request->validated(),
        ]);
    }
}

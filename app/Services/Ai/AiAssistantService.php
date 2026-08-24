<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderInterface;
use App\Models\Ai\AiAssistantInteraction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AiAssistantService
{
    public function __construct(
        private readonly AiIntentRouter $router,
        private readonly AiDateRangeResolver $dates,
        private readonly BusinessIntelligenceContextService $context,
        private readonly AiProviderInterface $provider,
    ) {}

    /** @return array<string, mixed> */
    public function ask(User $user, string $question, ?int $outletId, ?string $previousIntent, string $conversationId): array
    {
        $started = hrtime(true);
        $intent = $this->router->route($question, $previousIntent);
        $period = $this->dates->resolve($user, $question);

        if ($intent === 'advisory_only') {
            $draft = $this->advisoryOnly();
            $this->record($user, $outletId, $conversationId, $intent, 'deterministic', 'blocked', $question, $draft, $period, $started);

            return $draft + compact('intent', 'period');
        }

        try {
            $draft = $this->context->forIntent($user, $intent, $period, $outletId);
            $draft += ['intent' => $intent, 'period' => $period, 'advisory_only' => true];
            $providerName = 'deterministic';
            $status = 'completed';

            if ($this->provider->configured()) {
                try {
                    $wording = $this->provider->explain($this->providerDraft($draft), $question);
                    if ($wording) {
                        $draft = $this->mergeSafeWording($draft, $wording);
                        $providerName = $this->provider->name();
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                    $status = 'provider_fallback';
                }
            }

            $this->record($user, $outletId, $conversationId, $intent, $providerName, $status, $question, $draft, $period, $started);

            return $draft;
        } catch (\Throwable $exception) {
            report($exception);
            $response = $this->friendlyFailure() + ['intent' => $intent, 'period' => $period, 'advisory_only' => true];
            $this->record($user, $outletId, $conversationId, $intent, 'deterministic', 'failed', $question, $response, $period, $started, 'context_unavailable');

            return $response;
        }
    }

    /** @return array<string, mixed> */
    public function brief(User $user, ?int $outletId = null): array
    {
        return $this->context->forIntent($user, 'business_summary', $this->dates->resolve($user, 'today'), $outletId) + ['intent' => 'business_summary', 'advisory_only' => true];
    }

    private function providerDraft(array $draft): array
    {
        return Arr::only($draft, ['title', 'summary', 'facts', 'recommendations', 'coverage', 'followups']);
    }

    private function mergeSafeWording(array $draft, array $wording): array
    {
        foreach (['title', 'summary'] as $key) {
            if (is_string($wording[$key] ?? null)) {
                $draft[$key] = Str::limit(strip_tags($wording[$key]), $key === 'title' ? 120 : 600, '');
            }
        }
        foreach (['recommendations', 'followups'] as $key) {
            if (is_array($wording[$key] ?? null)) {
                $draft[$key] = collect($wording[$key])->filter('is_string')->map(fn (string $value) => Str::limit(strip_tags($value), 180, ''))->take($key === 'followups' ? 4 : 3)->values()->all();
            }
        }

        return $draft;
    }

    private function advisoryOnly(): array
    {
        return ['title' => 'I can help you review that decision', 'summary' => 'RetailPOS AI is read-only in this phase. I cannot create, change, send, transfer, refund, or delete business records.', 'facts' => [], 'recommendations' => ['Open the appropriate RetailPOS screen, review the source records, and complete the action yourself if it is correct.'], 'coverage' => 'No business data was changed.', 'sources' => [], 'followups' => ['What needs my attention?', 'How are sales today?'], 'fact_count' => 0, 'scope' => 'Advisory only', 'advisory_only' => true];
    }

    private function friendlyFailure(): array
    {
        return ['title' => 'I could not prepare that answer', 'summary' => 'Your RetailPOS data is safe. Please try again shortly or open the linked report directly.', 'facts' => [], 'recommendations' => ['Try a simpler question such as “How are sales today?”'], 'coverage' => 'No figures are shown because the authorized source data could not be read reliably.', 'sources' => [], 'followups' => ['How are sales today?', 'What needs my attention?'], 'fact_count' => 0, 'scope' => 'Unavailable'];
    }

    private function record(User $user, ?int $outletId, string $conversationId, string $intent, string $provider, string $status, string $question, array $response, array $period, int $started, ?string $error = null): void
    {
        AiAssistantInteraction::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'outlet_id' => $outletId,
            'conversation_id' => $conversationId,
            'intent' => $intent,
            'provider' => $provider,
            'model' => $provider === 'deterministic' ? null : config('ai.openai.model'),
            'status' => $status,
            'prompt_digest' => hash('sha256', Str::lower(trim($question))),
            'context_fact_count' => (int) ($response['fact_count'] ?? 0),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'safe_error_code' => $error,
            'date_scope' => $period,
        ]);
    }
}

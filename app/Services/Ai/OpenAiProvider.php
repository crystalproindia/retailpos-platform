<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    public function configured(): bool
    {
        return (bool) config('ai.enabled') && filled(config('ai.openai.api_key'));
    }

    public function name(): string
    {
        return 'openai';
    }

    public function explain(array $draft, string $question): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $response = Http::withToken((string) config('ai.openai.api_key'))
            ->acceptJson()
            ->timeout((int) config('ai.openai.timeout'))
            ->post((string) config('ai.openai.endpoint'), [
                'model' => config('ai.openai.model'),
                'max_output_tokens' => 700,
                'input' => [[
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Rewrite only the supplied RetailPOS facts in plain business English. Return JSON with title, summary, recommendations, and followups. Never add numbers, causes, actions, HTML, or facts. Keep all source facts unchanged and remain advisory only.',
                    ]],
                ], [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => json_encode(['question' => $question, 'approved_draft' => $draft], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI provider request failed.');
        }

        $text = data_get($response->json(), 'output.0.content.0.text');
        $decoded = is_string($text) ? json_decode($text, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}

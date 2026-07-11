<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('notifications.webhooks.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => ['string', Rule::in($this->webhookEnabledEventKeys())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $url = (string) $this->input('url');
                $scheme = parse_url($url, PHP_URL_SCHEME);
                $host = parse_url($url, PHP_URL_HOST);

                if (! in_array($scheme, ['https', 'http'], true)) {
                    $validator->errors()->add('url', 'Webhook URL must use HTTP or HTTPS.');
                }

                if (! $host || $this->isBlockedHost($host)) {
                    $validator->errors()->add('url', 'Webhook URL cannot target localhost or private network hosts.');
                }
            },
        ];
    }

    private function isBlockedHost(string $host): bool
    {
        $host = strtolower($host);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function webhookEnabledEventKeys(): array
    {
        return collect(config('events.catalog', []))
            ->filter(fn (array $definition): bool => (bool) ($definition['webhook_enabled'] ?? false))
            ->keys()
            ->values()
            ->all();
    }
}

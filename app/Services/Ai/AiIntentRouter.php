<?php

namespace App\Services\Ai;

class AiIntentRouter
{
    public const INTENTS = ['business_summary', 'sales_summary', 'sales_comparison', 'profitability', 'inventory', 'reorder', 'slow_stock', 'outlet_comparison', 'product_performance', 'customer_insight', 'crm_followup'];

    public function route(string $question, ?string $previousIntent = null): string
    {
        $value = str($question)->lower()->toString();

        if (preg_match('/\b(delete|create|issue|transfer|refund|pay|send|update|change|drop|insert|sql)\b/', $value)) {
            return 'advisory_only';
        }

        return match (true) {
            str_contains($value, 'reorder'), str_contains($value, 'running low') => 'reorder',
            str_contains($value, 'slow'), str_contains($value, 'dead stock'), str_contains($value, 'not moving') => 'slow_stock',
            str_contains($value, 'profit'), str_contains($value, 'margin'), str_contains($value, 'discount') => 'profitability',
            str_contains($value, 'inventory'), str_contains($value, 'stock value') => 'inventory',
            str_contains($value, 'outlet') => 'outlet_comparison',
            str_contains($value, 'product'), str_contains($value, 'best selling'), str_contains($value, 'selling well') => 'product_performance',
            str_contains($value, 'customer') => 'customer_insight',
            str_contains($value, 'lead'), str_contains($value, 'follow up'), str_contains($value, 'follow-up') => 'crm_followup',
            str_contains($value, 'compare'), str_contains($value, 'changed') => 'sales_comparison',
            str_contains($value, 'sale') => 'sales_summary',
            strlen(trim($value)) < 30 && $previousIntent && in_array($previousIntent, self::INTENTS, true) => $previousIntent,
            default => 'business_summary',
        };
    }
}

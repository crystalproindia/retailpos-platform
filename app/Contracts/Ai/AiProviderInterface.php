<?php

namespace App\Contracts\Ai;

interface AiProviderInterface
{
    /** @param array<string, mixed> $draft @return array<string, mixed>|null */
    public function explain(array $draft, string $question): ?array;

    public function configured(): bool;

    public function name(): string;
}

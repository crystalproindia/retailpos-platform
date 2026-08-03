<?php

namespace App\Jobs\Ai;

use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiForecastService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshAiForecastsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $companyId, public readonly string $type = 'all', public readonly ?int $actorId = null) {}

    public function uniqueId(): string
    {
        return "ai-forecast:{$this->companyId}:{$this->type}:".today()->toDateString();
    }

    public function handle(AiForecastService $forecasts): void
    {
        if (! Company::query()->whereKey($this->companyId)->exists()) return;

        $actor = $this->actorId ? User::query()->where('company_id', $this->companyId)->find($this->actorId) : null;
        $forecasts->run($this->companyId, $this->type, $actor);
    }
}

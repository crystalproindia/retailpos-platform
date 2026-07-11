<?php

namespace App\Repositories\Operations;

use App\Models\ScheduledTaskRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ScheduledTaskRunRepository
{
    /**
     * @return Collection<string, ScheduledTaskRun>
     */
    public function latestByCommand(): Collection
    {
        return ScheduledTaskRun::query()
            ->latest('started_at')
            ->get()
            ->unique('command')
            ->keyBy('command');
    }

    public function paginate(): LengthAwarePaginator
    {
        return ScheduledTaskRun::query()
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function prune(int $retentionDays): int
    {
        return ScheduledTaskRun::query()
            ->where('started_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}

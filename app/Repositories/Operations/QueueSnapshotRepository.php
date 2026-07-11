<?php

namespace App\Repositories\Operations;

use App\Models\QueueJobSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QueueSnapshotRepository
{
    public function latest(): ?QueueJobSnapshot
    {
        return QueueJobSnapshot::query()->latest('captured_at')->first();
    }

    public function paginate(): LengthAwarePaginator
    {
        return QueueJobSnapshot::query()
            ->latest('captured_at')
            ->paginate(15)
            ->withQueryString();
    }

    public function prune(int $retentionDays): int
    {
        return QueueJobSnapshot::query()
            ->where('captured_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}

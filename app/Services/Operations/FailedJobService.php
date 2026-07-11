<?php

namespace App\Services\Operations;

use App\Repositories\Operations\FailedJobRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class FailedJobService
{
    public function __construct(private readonly FailedJobRepository $failedJobs) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $jobs = $this->failedJobs->paginate($filters);

        $jobs->getCollection()->transform(fn (object $job): array => $this->summarize($job));

        return $jobs;
    }

    /**
     * @return Collection<int, string>
     */
    public function queues(): Collection
    {
        return $this->failedJobs->queues();
    }

    /**
     * @return array<string, mixed>
     */
    public function retry(int $id): array
    {
        $job = $this->failedJobs->find($id);

        Queue::connection($job->connection)->pushRaw($job->payload, $job->queue);
        $this->failedJobs->delete($id);

        return $this->summarize($job);
    }

    public function delete(int $id): int
    {
        return $this->failedJobs->delete($id);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function bulkRetry(array $ids): Collection
    {
        return $this->failedJobs->findMany($ids)
            ->map(function (object $job): array {
                Queue::connection($job->connection)->pushRaw($job->payload, $job->queue);
                $this->failedJobs->delete((int) $job->id);

                return $this->summarize($job);
            });
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        return $this->failedJobs->deleteMany($ids);
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(object $job): array
    {
        $payload = json_decode($job->payload, true);

        return [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'display_name' => is_array($payload) ? ($payload['displayName'] ?? $payload['job'] ?? 'Queued job') : 'Queued job',
            'payload_summary' => $this->payloadSummary($payload),
            'exception_preview' => $this->redactText(Str::limit((string) $job->exception, 900)),
            'failed_at' => $job->failed_at,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function payloadSummary(?array $payload): array
    {
        if (! $payload) {
            return ['summary' => 'Payload could not be decoded.'];
        }

        return [
            'uuid' => $payload['uuid'] ?? null,
            'displayName' => $payload['displayName'] ?? null,
            'job' => $payload['job'] ?? null,
            'maxTries' => $payload['maxTries'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'data_keys' => isset($payload['data']) && is_array($payload['data']) ? array_keys($payload['data']) : [],
        ];
    }

    private function redactText(string $value): string
    {
        $keys = implode('|', array_map('preg_quote', config('operations.sensitive_keys', [])));

        if ($keys === '') {
            return $value;
        }

        $value = preg_replace('/('.$keys.')(["\'\s:=]+)([^"\'\s,}]+)/i', '$1$2[redacted]', $value) ?? $value;

        return Str::limit($value, 900);
    }
}

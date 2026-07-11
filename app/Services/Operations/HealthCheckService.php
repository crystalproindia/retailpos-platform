<?php

namespace App\Services\Operations;

use App\Models\DomainEventLog;
use App\Models\NotificationDelivery;
use App\Models\SystemHealthCheck;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class HealthCheckService
{
    /**
     * @return Collection<int, SystemHealthCheck>
     */
    public function runAll(): Collection
    {
        return collect([
            $this->check('app_boot', 'Application boot', 'Application', fn (): array => [
                'status' => 'healthy',
                'message' => 'Application container booted successfully.',
                'payload' => ['app_name' => config('app.name')],
            ]),
            $this->check('database', 'Database connection', 'Infrastructure', fn (): array => $this->databaseCheck()),
            $this->check('cache', 'Cache connection', 'Infrastructure', fn (): array => $this->cacheCheck()),
            $this->check('queue', 'Queue connection', 'Infrastructure', fn (): array => $this->queueCheck()),
            $this->check('mail', 'Mail configuration', 'Infrastructure', fn (): array => $this->mailCheck()),
            $this->check('storage', 'Storage write/read', 'Infrastructure', fn (): array => $this->storageCheck()),
            $this->check('scheduler', 'Scheduler availability', 'Operations', fn (): array => $this->schedulerCheck()),
            $this->countCheck('failed_jobs', 'Failed jobs', 'Queue', $this->tableCount('failed_jobs')),
            $this->countCheck('notification_delivery_failures', 'Notification delivery failures', 'Notifications', $this->modelCount(NotificationDelivery::class, ['status' => 'failed'])),
            $this->countCheck('webhook_delivery_failures', 'Webhook delivery failures', 'Notifications', $this->modelCount(WebhookDelivery::class, ['status' => 'failed'])),
            $this->countCheck('domain_event_failures', 'Domain event failures', 'Events', $this->modelCount(DomainEventLog::class, ['status' => 'failed'])),
            $this->check('php_version', 'PHP version', 'Runtime', fn (): array => [
                'status' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'healthy' : 'warning',
                'message' => 'PHP '.PHP_VERSION,
                'payload' => ['version' => PHP_VERSION],
            ]),
            $this->check('laravel_version', 'Laravel version', 'Runtime', fn (): array => [
                'status' => 'healthy',
                'message' => 'Laravel '.app()->version(),
                'payload' => ['version' => app()->version()],
            ]),
            $this->check('node_build', 'Node build status', 'Runtime', fn (): array => $this->nodeBuildCheck()),
            $this->check('environment', 'Environment sanity', 'Configuration', fn (): array => $this->environmentCheck()),
        ])->map(fn (array $result): SystemHealthCheck => SystemHealthCheck::create($result));
    }

    public function overallStatus(Collection $checks): string
    {
        $statuses = $checks->pluck('status');

        if ($statuses->contains('critical')) {
            return 'critical';
        }

        if ($statuses->contains('warning')) {
            return 'warning';
        }

        if ($statuses->contains('unknown')) {
            return 'unknown';
        }

        return 'healthy';
    }

    /**
     * @return array<string, mixed>
     */
    private function check(string $key, string $name, string $category, callable $callback): array
    {
        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $result = [
                'status' => 'critical',
                'message' => Str::limit($exception->getMessage(), 500),
                'payload' => ['exception' => $exception::class],
            ];
        }

        return [
            'company_id' => null,
            'key' => $key,
            'name' => $name,
            'category' => $category,
            'status' => $result['status'] ?? 'unknown',
            'message' => $result['message'] ?? null,
            'payload' => $result['payload'] ?? null,
            'checked_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countCheck(string $key, string $name, string $category, int $count): array
    {
        $status = match (true) {
            $count >= 10 => 'critical',
            $count > 0 => 'warning',
            default => 'healthy',
        };

        return [
            'company_id' => null,
            'key' => $key,
            'name' => $name,
            'category' => $category,
            'status' => $status,
            'message' => "{$count} issue".($count === 1 ? '' : 's').' found.',
            'payload' => ['count' => $count],
            'checked_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        DB::connection()->select('select 1 as health_check');

        return [
            'status' => 'healthy',
            'message' => 'Database query completed.',
            'payload' => ['driver' => config('database.default')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheCheck(): array
    {
        $key = 'operations_health_'.Str::random(8);

        Cache::put($key, 'ok', 60);
        $value = Cache::get($key);
        Cache::forget($key);

        return [
            'status' => $value === 'ok' ? 'healthy' : 'critical',
            'message' => $value === 'ok' ? 'Cache write/read completed.' : 'Cache value could not be read back.',
            'payload' => ['store' => config('cache.default')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueCheck(): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver", 'unknown');
        $pendingCount = $this->tableCount('jobs', ['reserved_at' => null]);

        return [
            'status' => $driver === 'unknown' ? 'warning' : 'healthy',
            'message' => "Queue connection {$connection} uses {$driver}.",
            'payload' => [
                'connection' => $connection,
                'driver' => $driver,
                'pending_count' => $pendingCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mailCheck(): array
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport", 'unknown');
        $fromAddress = config('mail.from.address');

        return [
            'status' => $fromAddress ? 'healthy' : 'warning',
            'message' => $fromAddress ? "Mailer {$mailer} is configured." : 'Mail from address is not configured.',
            'payload' => [
                'mailer' => $mailer,
                'transport' => $transport,
                'from_configured' => (bool) $fromAddress,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FileNotFoundException
     */
    private function storageCheck(): array
    {
        $disk = config('filesystems.default');
        $path = 'operations/health-'.Str::random(8).'.txt';

        Storage::disk($disk)->put($path, 'ok');
        $value = Storage::disk($disk)->get($path);
        Storage::disk($disk)->delete($path);

        return [
            'status' => $value === 'ok' ? 'healthy' : 'critical',
            'message' => $value === 'ok' ? 'Storage write/read completed.' : 'Storage value could not be read back.',
            'payload' => ['disk' => $disk],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerCheck(): array
    {
        $commands = config('operations.scheduled_commands', []);

        return [
            'status' => file_exists(base_path('routes/console.php')) && $commands ? 'healthy' : 'unknown',
            'message' => count($commands).' scheduled command definitions configured.',
            'payload' => ['scheduled_command_count' => count($commands)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeBuildCheck(): array
    {
        $manifest = public_path('build/manifest.json');

        return [
            'status' => file_exists($manifest) ? 'healthy' : 'warning',
            'message' => file_exists($manifest) ? 'Vite manifest is present.' : 'Vite manifest has not been built yet.',
            'payload' => [
                'manifest_present' => file_exists($manifest),
                'manifest_updated_at' => file_exists($manifest) ? date(DATE_ATOM, filemtime($manifest)) : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentCheck(): array
    {
        $isProductionDebug = app()->environment('production') && config('app.debug');
        $hasAppKey = filled(config('app.key'));

        return [
            'status' => $isProductionDebug || ! $hasAppKey ? 'critical' : 'healthy',
            'message' => match (true) {
                $isProductionDebug => 'Debug mode is enabled in production.',
                ! $hasAppKey => 'Application key is missing.',
                default => 'Environment sanity checks passed.',
            },
            'payload' => [
                'environment' => app()->environment(),
                'debug_enabled' => (bool) config('app.debug'),
                'app_key_configured' => $hasAppKey,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function tableCount(string $table, array $conditions = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        foreach ($conditions as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query->count();
    }

    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $conditions
     */
    private function modelCount(string $model, array $conditions): int
    {
        $query = $model::query();

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
    }
}

<?php

namespace App\Console\Commands\RetailPos;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LiveReadinessCheckCommand extends Command
{
    protected $signature = 'retailpos:live-check';

    protected $description = 'Run read-only RetailPOS production and demo readiness checks.';

    private int $passed = 0;

    private int $warnings = 0;

    private int $failures = 0;

    public function handle(): int
    {
        $this->info('RetailPOS live readiness check');
        $this->newLine();

        $this->record('Application environment', fn (): array => config('app.env') === 'production'
            ? ['PASS', 'production']
            : ['WARN', 'expected production, found '.config('app.env')]);

        $this->record('Application debug', fn (): array => config('app.debug') === false
            ? ['PASS', 'disabled']
            : ['WARN', 'APP_DEBUG should be false']);

        $this->record('Application URL', function (): array {
            $url = (string) config('app.url');

            return str_starts_with($url, 'https://')
                ? ['PASS', $url]
                : ['WARN', 'APP_URL should use HTTPS'];
        });

        $this->record('PHP version', fn (): array => version_compare(PHP_VERSION, '8.3.0', '>=')
            ? ['PASS', PHP_VERSION]
            : ['FAIL', 'PHP 8.3 or newer is required']);

        $this->record('Database connection', function (): array {
            DB::connection()->getPdo();

            return ['PASS', (string) config('database.default')];
        });

        $this->record('Migration table', fn (): array => Schema::hasTable('migrations')
            ? ['PASS', 'available']
            : ['FAIL', 'migrations table is missing']);

        $this->record('Storage writable', fn (): array => is_writable(storage_path())
            ? ['PASS', 'storage']
            : ['FAIL', 'storage is not writable']);

        $this->record('Log directory writable', fn (): array => is_dir(storage_path('logs')) && is_writable(storage_path('logs'))
            ? ['PASS', 'storage/logs']
            : ['FAIL', 'storage/logs is not writable']);

        $this->record('Bootstrap cache writable', fn (): array => is_writable(base_path('bootstrap/cache'))
            ? ['PASS', 'bootstrap/cache']
            : ['FAIL', 'bootstrap/cache is not writable']);

        $this->record('Public storage link', fn (): array => is_link(public_path('storage'))
            ? ['PASS', 'public/storage']
            : ['WARN', 'run php artisan storage:link if public media is required']);

        $this->record('Configuration cache', fn (): array => app()->configurationIsCached()
            ? ['PASS', 'cached']
            : ['WARN', 'configuration is not cached']);

        $this->record('Route cache', fn (): array => app()->routesAreCached()
            ? ['PASS', 'cached']
            : ['WARN', 'routes are not cached']);

        $this->record('POS routes', function (): array {
            $routes = ['pos.index', 'pos.terminal', 'pos.mobile'];
            $missing = collect($routes)->reject(fn (string $route): bool => Route::has($route));

            return $missing->isEmpty()
                ? ['PASS', implode(', ', $routes)]
                : ['FAIL', 'missing '.implode(', ', $missing->all())];
        });

        $this->record('Offline POS routes', function (): array {
            $routes = ['pos.offline.index', 'pos.offline.bootstrap', 'pos.offline.sync'];
            $missing = collect($routes)->reject(fn (string $route): bool => Route::has($route));

            return $missing->isEmpty()
                ? ['PASS', implode(', ', $routes)]
                : ['FAIL', 'missing '.implode(', ', $missing->all())];
        });

        $this->record('Queue connection', function (): array {
            $connection = (string) config('queue.default');

            return $connection === 'database'
                ? ['PASS', $connection]
                : ['WARN', 'expected database, found '.$connection];
        });

        $this->record('Scheduler commands', function (): array {
            $commands = Artisan::all();
            $required = ['schedule:run', 'operations:health-check', 'operations:capture-queue-snapshot'];
            $missing = collect($required)->reject(fn (string $command): bool => array_key_exists($command, $commands));

            return $missing->isEmpty()
                ? ['PASS', implode(', ', $required)]
                : ['FAIL', 'missing '.implode(', ', $missing->all())];
        });

        $this->record('Vite build manifest', fn (): array => is_file(public_path('build/manifest.json'))
            ? ['PASS', 'public/build/manifest.json']
            : ['WARN', 'build assets are missing']);

        $this->newLine();
        $this->line("Summary: {$this->passed} PASS, {$this->warnings} WARN, {$this->failures} FAIL");

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  callable(): array{0: 'PASS'|'WARN'|'FAIL', 1: string}  $check
     */
    private function record(string $label, callable $check): void
    {
        try {
            [$status, $detail] = $check();
        } catch (Throwable) {
            $status = 'FAIL';
            $detail = 'check could not be completed';
        }

        match ($status) {
            'PASS' => $this->passed++,
            'WARN' => $this->warnings++,
            default => $this->failures++,
        };

        $this->line("[{$status}] {$label}: {$detail}");
    }
}

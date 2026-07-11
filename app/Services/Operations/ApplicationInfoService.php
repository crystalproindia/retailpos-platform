<?php

namespace App\Services\Operations;

use DateTimeZone;

class ApplicationInfoService
{
    /**
     * @return array<string, array{label: string, value: mixed}>
     */
    public function info(): array
    {
        $git = $this->gitInfo();
        $manifest = public_path('build/manifest.json');

        return [
            'app_name' => ['label' => 'App name', 'value' => config('app.name')],
            'environment' => ['label' => 'Environment', 'value' => app()->environment()],
            'debug' => ['label' => 'Debug mode', 'value' => config('app.debug') ? 'Enabled' : 'Disabled'],
            'laravel_version' => ['label' => 'Laravel version', 'value' => app()->version()],
            'php_version' => ['label' => 'PHP version', 'value' => PHP_VERSION],
            'database_driver' => ['label' => 'Database driver', 'value' => config('database.default')],
            'cache_driver' => ['label' => 'Cache driver', 'value' => config('cache.default')],
            'queue_driver' => ['label' => 'Queue driver', 'value' => config('queue.default')],
            'mail_driver' => ['label' => 'Mail driver', 'value' => config('mail.default')],
            'filesystem_disk' => ['label' => 'Filesystem disk', 'value' => config('filesystems.default')],
            'git_commit' => ['label' => 'Git commit hash', 'value' => $git['commit']],
            'git_branch' => ['label' => 'Current branch', 'value' => $git['branch']],
            'last_deployment' => ['label' => 'Last deployment time', 'value' => file_exists($manifest) ? date('Y-m-d H:i:s T', filemtime($manifest)) : 'Unavailable'],
            'app_timezone' => ['label' => 'App timezone', 'value' => config('app.timezone')],
            'server_timezone' => ['label' => 'Server timezone', 'value' => (new DateTimeZone(date_default_timezone_get()))->getName()],
        ];
    }

    /**
     * @return array{branch: string, commit: string}
     */
    private function gitInfo(): array
    {
        $headPath = base_path('.git/HEAD');

        if (! is_readable($headPath)) {
            return ['branch' => 'Unavailable', 'commit' => 'Unavailable'];
        }

        $head = trim((string) file_get_contents($headPath));

        if (! str_starts_with($head, 'ref: ')) {
            return ['branch' => 'Detached', 'commit' => substr($head, 0, 12) ?: 'Unavailable'];
        }

        $ref = substr($head, 5);
        $branch = basename($ref);
        $refPath = base_path('.git/'.$ref);
        $commit = is_readable($refPath) ? trim((string) file_get_contents($refPath)) : 'Unavailable';

        return ['branch' => $branch, 'commit' => substr($commit, 0, 12) ?: 'Unavailable'];
    }
}

<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __invoke(Request $request, string $module): View
    {
        abort_unless($this->moduleExists($module), 404);

        return view('command-center.modules.show', [
            'module' => $module,
            'title' => $this->title($module),
            'auditLogs' => $module === 'audit-logs'
                ? AuditLog::query()
                    ->with('user')
                    ->where('company_id', $request->user()->company_id)
                    ->latest('created_at')
                    ->paginate(12)
                : null,
        ]);
    }

    private function moduleExists(string $module): bool
    {
        return collect(config('command-center.navigation'))
            ->contains(fn (array $item) => Arr::get($item, 'params.module') === $module);
    }

    private function title(string $module): string
    {
        return collect(config('command-center.navigation'))
            ->firstWhere('params.module', $module)['label'] ?? Str::of($module)->replace('-', ' ')->headline()->toString();
    }
}

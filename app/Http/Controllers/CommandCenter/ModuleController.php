<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __invoke(Request $request, ModuleRegistry $moduleRegistry, string $module): View
    {
        $registeredModule = $moduleRegistry->find($module);

        abort_unless($registeredModule?->enabled, 404);
        abort_unless($registeredModule->allowedFor($request->user()->role), 403);

        return view('command-center.modules.show', [
            'module' => $module,
            'title' => $registeredModule->name,
            'auditLogs' => $module === 'audit-logs'
                ? AuditLog::query()
                    ->with('user')
                    ->where('company_id', $request->user()->company_id)
                    ->latest('created_at')
                    ->paginate(12)
                : null,
        ]);
    }
}

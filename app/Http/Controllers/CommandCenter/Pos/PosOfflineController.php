<?php

namespace App\Http\Controllers\CommandCenter\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosOfflineSyncRequest;
use App\Models\Pos\PosOfflineSyncRecord;
use App\Services\AuditLogger;
use App\Services\Pos\PosOfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PosOfflineController extends Controller
{
    public function bootstrap(Request $request, PosOfflineSyncService $offline): JsonResponse { return response()->json($offline->bootstrap($request->user())); }
    public function status(Request $request, PosOfflineSyncService $offline): JsonResponse { return response()->json(['online' => true, 'server_time' => now()->toIso8601String(), 'settings' => $offline->settings($request->user()->company_id)->only(['enable_offline_pos', 'enable_auto_sync', 'allow_offline_cash', 'allow_offline_manual_card', 'allow_offline_manual_upi'])]); }
    public function sync(PosOfflineSyncRequest $request, PosOfflineSyncService $offline): JsonResponse { return response()->json($offline->sync($request->user(), $request->validated())); }

    public function index(Request $request): View
    {
        $records = $this->recordsQuery($request)->latest()->paginate(25)->withQueryString();
        return view('command-center.pos.offline.index', compact('records'));
    }

    public function records(Request $request): JsonResponse
    {
        return response()->json(['records' => $this->recordsQuery($request)->latest()->limit(100)->get()]);
    }

    public function retry(Request $request, PosOfflineSyncRecord $record, PosOfflineSyncService $offline, AuditLogger $audit): RedirectResponse
    {
        abort_unless($record->company_id === $request->user()->company_id, 404);
        $result = $offline->sync($request->user(), ['batch_uuid' => (string) Str::uuid(), 'device_id' => $record->device_id, 'records' => [$record->payload]]);
        $audit->record('pos.offline.sync.retried', $record, 'Offline sync record retried');
        return back()->with('status', $result['results'][0]['status'] === 'failed' ? 'Offline record remains unsynced.' : 'Offline record sync retried.');
    }

    private function recordsQuery(Request $request)
    {
        $query = PosOfflineSyncRecord::query()->with(['user'])->where('company_id', $request->user()->company_id);
        if (! $request->user()->hasAnyRole(['administrator', 'manager'])) $query->where('user_id', $request->user()->id);
        return $query;
    }
}

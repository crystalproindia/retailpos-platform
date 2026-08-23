<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySettingsController extends Controller
{
    public function index(Request $request): View
    {
        $settings = Setting::query()
            ->where('company_id', $request->user()->company_id)
            ->where('group', 'inventory')
            ->pluck('value', 'key');

        return view('command-center.inventory.settings.index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'default_cost_method' => ['required', 'string', 'max:80'],
            'low_stock_notifications' => ['nullable', 'boolean'],
            'allow_negative_stock_default' => ['nullable', 'boolean'],
            'require_transfer_approval' => ['nullable', 'boolean'],
            'enable_transfer_packing' => ['nullable', 'boolean'],
            'large_adjustment_threshold' => ['nullable', 'numeric', 'min:0'],
            'barcode_price_source' => ['required', 'string', 'max:80'],
            'dead_stock_days' => ['required', 'integer', 'min:30', 'max:730'],
            'new_stock_grace_days' => ['required', 'integer', 'min:1', 'max:180'],
            'slow_mover_max_units' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'fast_mover_min_units' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'fast_mover_min_daily_velocity' => ['required', 'numeric', 'min:0.001', 'max:1000000'],
            'default_lead_time_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        foreach (['low_stock_notifications', 'allow_negative_stock_default', 'require_transfer_approval', 'enable_transfer_packing'] as $key) {
            $validated[$key] = $request->boolean($key);
        }

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['company_id' => $request->user()->company_id, 'group' => 'inventory', 'key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        $auditLogger->record('inventory.settings.updated', null, 'Inventory settings updated', ['company_id' => $request->user()->company_id]);

        return back()->with('status', 'Inventory settings updated.');
    }
}

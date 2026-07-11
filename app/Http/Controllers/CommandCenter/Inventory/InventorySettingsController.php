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
            'barcode_price_source' => ['required', 'string', 'max:80'],
        ]);

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

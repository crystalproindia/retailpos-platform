<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryUnit;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryUnitController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.catalog.index', [
            'title' => 'Units',
            'routePrefix' => 'inventory.units',
            'items' => InventoryUnit::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->withTrashed()->orderBy('name')->paginate(20),
            'fields' => ['short_code', 'type'],
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Create Unit',
            'routePrefix' => 'inventory.units',
            'item' => new InventoryUnit(['is_active' => true, 'type' => 'quantity']),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $unit = InventoryUnit::create($this->validated($request) + ['company_id' => $request->user()->company_id]);
        $auditLogger->record('inventory.unit.created', $unit, 'Inventory unit created');

        return redirect()->route('inventory.units.index')->with('status', 'Unit created.');
    }

    public function edit(Request $request, int $unit): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Edit Unit',
            'routePrefix' => 'inventory.units',
            'item' => InventoryUnit::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->withTrashed()->findOrFail($unit),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $unit): RedirectResponse
    {
        $item = InventoryUnit::query()->where('company_id', $request->user()->company_id)->findOrFail($unit);
        $item->update($this->validated($request));
        $auditLogger->record('inventory.unit.updated', $item, 'Inventory unit updated');

        return back()->with('status', 'Unit updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $unit): RedirectResponse
    {
        $item = InventoryUnit::query()->where('company_id', $request->user()->company_id)->findOrFail($unit);
        $item->delete();
        $auditLogger->record('inventory.unit.deleted', $item, 'Inventory unit moved to trash');

        return back()->with('status', 'Unit moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $unit): RedirectResponse
    {
        $item = InventoryUnit::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($unit);
        $item->restore();
        $auditLogger->record('inventory.unit.restored', $item, 'Inventory unit restored');

        return back()->with('status', 'Unit restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:40'],
            'type' => ['required', 'string', 'max:80'],
            'decimal_allowed' => ['nullable', 'boolean'],
            'conversion_factor' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['decimal_allowed'] = (bool) ($data['decimal_allowed'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

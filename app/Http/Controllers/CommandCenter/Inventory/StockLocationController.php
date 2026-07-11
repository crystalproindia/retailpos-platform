<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockLocationController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.locations.index', [
            'locations' => StockLocation::query()->with('warehouse')->where('company_id', $request->user()->company_id)->withTrashed()->orderBy('code')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return view('command-center.inventory.locations.form', [
            'location' => new StockLocation(['is_active' => true, 'type' => 'bin']),
            'warehouses' => Warehouse::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $location = StockLocation::create($this->validated($request) + ['company_id' => $request->user()->company_id]);
        $auditLogger->record('inventory.stock_location.created', $location, 'Inventory stock location created');

        return redirect()->route('inventory.locations.index')->with('status', 'Stock location created.');
    }

    public function edit(Request $request, int $location): View
    {
        return view('command-center.inventory.locations.form', [
            'location' => StockLocation::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($location),
            'warehouses' => Warehouse::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $location): RedirectResponse
    {
        $model = StockLocation::query()->where('company_id', $request->user()->company_id)->findOrFail($location);
        $model->update($this->validated($request));
        $auditLogger->record('inventory.stock_location.updated', $model, 'Inventory stock location updated');

        return back()->with('status', 'Stock location updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $location): RedirectResponse
    {
        $model = StockLocation::query()->where('company_id', $request->user()->company_id)->findOrFail($location);
        $model->delete();
        $auditLogger->record('inventory.stock_location.deleted', $model, 'Inventory stock location moved to trash');

        return back()->with('status', 'Stock location moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $location): RedirectResponse
    {
        $model = StockLocation::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($location);
        $model->restore();
        $auditLogger->record('inventory.stock_location.restored', $model, 'Inventory stock location restored');

        return back()->with('status', 'Stock location restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:80'],
            'aisle' => ['nullable', 'string', 'max:80'],
            'rack' => ['nullable', 'string', 'max:80'],
            'shelf' => ['nullable', 'string', 'max:80'],
            'bin' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

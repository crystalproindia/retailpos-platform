<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory\Warehouse;
use App\Services\AuditLogger;
use App\Services\Saas\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.warehouses.index', [
            'warehouses' => Warehouse::query()->with('branch')->where('company_id', $request->user()->company_id)->withTrashed()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return view('command-center.inventory.warehouses.form', [
            'warehouse' => new Warehouse(['is_active' => true, 'country' => 'India', 'type' => 'store']),
            'branches' => Branch::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger, UsageService $usage): RedirectResponse
    {
        $warehouse = DB::transaction(function () use ($request, $usage): Warehouse {
            $usage->assertWithinLimit($request->user()->company, 'warehouses');

            return Warehouse::create($this->validated($request) + ['company_id' => $request->user()->company_id]);
        });
        $auditLogger->record('inventory.warehouse.created', $warehouse, 'Inventory warehouse created');

        return redirect()->route('inventory.warehouses.index')->with('status', 'Warehouse created.');
    }

    public function edit(Request $request, int $warehouse): View
    {
        return view('command-center.inventory.warehouses.form', [
            'warehouse' => Warehouse::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($warehouse),
            'branches' => Branch::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $warehouse): RedirectResponse
    {
        $model = Warehouse::query()->where('company_id', $request->user()->company_id)->findOrFail($warehouse);
        $model->update($this->validated($request));
        $auditLogger->record('inventory.warehouse.updated', $model, 'Inventory warehouse updated');

        return back()->with('status', 'Warehouse updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $warehouse): RedirectResponse
    {
        $model = Warehouse::query()->where('company_id', $request->user()->company_id)->findOrFail($warehouse);
        $model->delete();
        $auditLogger->record('inventory.warehouse.deleted', $model, 'Inventory warehouse moved to trash');

        return back()->with('status', 'Warehouse moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $warehouse): RedirectResponse
    {
        $model = Warehouse::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($warehouse);
        $model->restore();
        $auditLogger->record('inventory.warehouse.restored', $model, 'Inventory warehouse restored');

        return back()->with('status', 'Warehouse restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'max:80'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

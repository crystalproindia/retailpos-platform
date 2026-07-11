<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryTaxRate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryTaxRateController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.catalog.index', [
            'title' => 'Tax Rates',
            'routePrefix' => 'inventory.tax-rates',
            'items' => InventoryTaxRate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->withTrashed()->orderBy('rate')->paginate(20),
            'fields' => ['rate', 'tax_type', 'state'],
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Create Tax Rate',
            'routePrefix' => 'inventory.tax-rates',
            'item' => new InventoryTaxRate(['is_active' => true, 'tax_type' => 'gst', 'country' => 'India']),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $taxRate = InventoryTaxRate::create($this->validated($request) + ['company_id' => $request->user()->company_id]);
        $auditLogger->record('inventory.tax_rate.created', $taxRate, 'Inventory tax rate created');

        return redirect()->route('inventory.tax-rates.index')->with('status', 'Tax rate created.');
    }

    public function edit(Request $request, int $tax_rate): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Edit Tax Rate',
            'routePrefix' => 'inventory.tax-rates',
            'item' => InventoryTaxRate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->withTrashed()->findOrFail($tax_rate),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $tax_rate): RedirectResponse
    {
        $item = InventoryTaxRate::query()->where('company_id', $request->user()->company_id)->findOrFail($tax_rate);
        $item->update($this->validated($request));
        $auditLogger->record('inventory.tax_rate.updated', $item, 'Inventory tax rate updated');

        return back()->with('status', 'Tax rate updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $tax_rate): RedirectResponse
    {
        $item = InventoryTaxRate::query()->where('company_id', $request->user()->company_id)->findOrFail($tax_rate);
        $item->delete();
        $auditLogger->record('inventory.tax_rate.deleted', $item, 'Inventory tax rate moved to trash');

        return back()->with('status', 'Tax rate moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $tax_rate): RedirectResponse
    {
        $item = InventoryTaxRate::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($tax_rate);
        $item->restore();
        $auditLogger->record('inventory.tax_rate.restored', $item, 'Inventory tax rate restored');

        return back()->with('status', 'Tax rate restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0'],
            'tax_type' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

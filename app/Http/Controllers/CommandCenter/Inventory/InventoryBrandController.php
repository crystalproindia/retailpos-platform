<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBrand;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryBrandController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.catalog.index', [
            'title' => 'Brands',
            'routePrefix' => 'inventory.brands',
            'items' => InventoryBrand::query()->where('company_id', $request->user()->company_id)->withTrashed()->orderBy('name')->paginate(20),
            'fields' => ['description'],
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Create Brand',
            'routePrefix' => 'inventory.brands',
            'item' => new InventoryBrand(['is_active' => true]),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $brand = InventoryBrand::create($data + ['company_id' => $request->user()->company_id, 'slug' => Str::slug($data['slug'] ?? $data['name'])]);
        $auditLogger->record('inventory.brand.created', $brand, 'Inventory brand created');

        return redirect()->route('inventory.brands.index')->with('status', 'Brand created.');
    }

    public function edit(Request $request, int $brand): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Edit Brand',
            'routePrefix' => 'inventory.brands',
            'item' => InventoryBrand::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($brand),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $brand): RedirectResponse
    {
        $item = InventoryBrand::query()->where('company_id', $request->user()->company_id)->findOrFail($brand);
        $data = $this->validated($request);
        $item->update($data + ['slug' => Str::slug($data['slug'] ?? $data['name'])]);
        $auditLogger->record('inventory.brand.updated', $item, 'Inventory brand updated');

        return back()->with('status', 'Brand updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $brand): RedirectResponse
    {
        $item = InventoryBrand::query()->where('company_id', $request->user()->company_id)->findOrFail($brand);
        $item->delete();
        $auditLogger->record('inventory.brand.deleted', $item, 'Inventory brand moved to trash');

        return back()->with('status', 'Brand moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $brand): RedirectResponse
    {
        $item = InventoryBrand::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($brand);
        $item->restore();
        $auditLogger->record('inventory.brand.restored', $item, 'Inventory brand restored');

        return back()->with('status', 'Brand restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

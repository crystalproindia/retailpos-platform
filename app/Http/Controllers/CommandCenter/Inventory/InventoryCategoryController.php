<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.catalog.index', [
            'title' => 'Categories',
            'routePrefix' => 'inventory.categories',
            'items' => InventoryCategory::query()->with('parent')->where('company_id', $request->user()->company_id)->withTrashed()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'fields' => ['description', 'sort_order'],
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Create Category',
            'routePrefix' => 'inventory.categories',
            'item' => new InventoryCategory(['is_active' => true]),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $category = InventoryCategory::create($data + ['company_id' => $request->user()->company_id, 'slug' => Str::slug($data['slug'] ?? $data['name'])]);
        $auditLogger->record('inventory.category.created', $category, 'Inventory category created');

        return redirect()->route('inventory.categories.index')->with('status', 'Category created.');
    }

    public function edit(Request $request, int $category): View
    {
        return view('command-center.inventory.catalog.form', [
            'title' => 'Edit Category',
            'routePrefix' => 'inventory.categories',
            'item' => InventoryCategory::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($category),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, int $category): RedirectResponse
    {
        $item = InventoryCategory::query()->where('company_id', $request->user()->company_id)->findOrFail($category);
        $data = $this->validated($request);
        $item->update($data + ['slug' => Str::slug($data['slug'] ?? $data['name'])]);
        $auditLogger->record('inventory.category.updated', $item, 'Inventory category updated');

        return back()->with('status', 'Category updated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger, int $category): RedirectResponse
    {
        $item = InventoryCategory::query()->where('company_id', $request->user()->company_id)->findOrFail($category);
        $item->delete();
        $auditLogger->record('inventory.category.deleted', $item, 'Inventory category moved to trash');

        return back()->with('status', 'Category moved to trash.');
    }

    public function restore(Request $request, AuditLogger $auditLogger, int $category): RedirectResponse
    {
        $item = InventoryCategory::query()->where('company_id', $request->user()->company_id)->withTrashed()->findOrFail($category);
        $item->restore();
        $auditLogger->record('inventory.category.restored', $item, 'Inventory category restored');

        return back()->with('status', 'Category restored.');
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
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}

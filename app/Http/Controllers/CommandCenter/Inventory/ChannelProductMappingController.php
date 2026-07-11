<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ChannelProductMapping;
use App\Models\Inventory\SalesChannel;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\ChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChannelProductMappingController extends Controller
{
    public function index(Request $request, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        $options = $lookups->formOptions($request->user()->company_id);

        return view('command-center.inventory.channels.mappings', [
            'channels' => SalesChannel::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
            'mappings' => ChannelProductMapping::query()->with(['salesChannel', 'product'])->where('company_id', $request->user()->company_id)->latest()->paginate(20),
            'products' => $products->activeForCompany($request->user()->company_id),
            'warehouses' => $options['warehouses'],
        ]);
    }

    public function store(Request $request, ChannelService $channels): RedirectResponse
    {
        $validated = $request->validate([
            'sales_channel_id' => ['required', 'integer', 'exists:sales_channels,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'channel_sku' => ['nullable', 'string', 'max:255'],
            'channel_product_name' => ['nullable', 'string', 'max:255'],
            'channel_price' => ['nullable', 'numeric', 'min:0'],
            'channel_mrp' => ['nullable', 'numeric', 'min:0'],
            'channel_offer_price' => ['nullable', 'numeric', 'min:0'],
            'stock_buffer_quantity' => ['nullable', 'numeric', 'min:0'],
            'max_listed_quantity' => ['nullable', 'numeric', 'min:0'],
            'listed_quantity' => ['nullable', 'numeric', 'min:0'],
            'reserved_quantity' => ['nullable', 'numeric', 'min:0'],
            'available_quantity' => ['nullable', 'numeric', 'min:0'],
            'sync_product' => ['nullable', 'boolean'],
            'sync_price' => ['nullable', 'boolean'],
            'sync_stock' => ['nullable', 'boolean'],
        ]);

        $channel = SalesChannel::query()->where('company_id', $request->user()->company_id)->findOrFail($validated['sales_channel_id']);
        $channels->saveMapping($request->user(), $channel, $validated);

        return back()->with('status', 'Channel mapping saved.');
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SalesChannelRequest;
use App\Models\Inventory\SalesChannel;
use App\Repositories\Inventory\ChannelRepository;
use App\Services\Inventory\ChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesChannelController extends Controller
{
    public function index(Request $request, ChannelRepository $channels): View
    {
        return view('command-center.inventory.channels.index', [
            'channels' => $channels->paginate($request->user()->company_id),
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.channels.form', [
            'channel' => new SalesChannel(['type' => 'store', 'price_strategy' => 'selling_price', 'stock_strategy' => 'available_stock', 'is_active' => true]),
        ]);
    }

    public function store(SalesChannelRequest $request, ChannelService $channels): RedirectResponse
    {
        $channels->saveChannel($request->user(), $request->validated());

        return redirect()->route('inventory.channels.index')->with('status', 'Sales channel created.');
    }

    public function edit(Request $request, int $channel): View
    {
        return view('command-center.inventory.channels.form', [
            'channel' => SalesChannel::query()->where('company_id', $request->user()->company_id)->findOrFail($channel),
        ]);
    }

    public function update(SalesChannelRequest $request, ChannelService $channels, int $channel): RedirectResponse
    {
        $model = SalesChannel::query()->where('company_id', $request->user()->company_id)->findOrFail($channel);
        $channels->saveChannel($request->user(), $request->validated(), $model);

        return back()->with('status', 'Sales channel updated.');
    }

    public function warning(Request $request, ChannelService $channels, int $channel): RedirectResponse
    {
        $model = SalesChannel::query()->where('company_id', $request->user()->company_id)->findOrFail($channel);
        $channels->logWarning($request->user(), $model, 'Manual channel sync warning recorded for operations review.');

        return back()->with('status', 'Channel warning logged.');
    }
}

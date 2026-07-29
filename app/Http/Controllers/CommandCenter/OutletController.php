<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\OutletRepository;
use App\Services\Outlets\OutletAccessService;
use App\Services\Outlets\OutletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OutletController extends Controller
{
    public function index(Request $request, OutletRepository $outlets): View
    {
        return view('command-center.outlets.index', ['outlets' => $outlets->forCompany($request->user()), 'limit' => app(\App\Services\Saas\EntitlementService::class)->limit($request->user()->company, 'branches')]);
    }

    public function create(): View { return view('command-center.outlets.form', ['outlet' => null, 'users' => collect()]); }

    public function store(Request $request, OutletService $outlets): RedirectResponse
    {
        $outlet = $outlets->create($request->user(), $this->validated($request));
        return redirect()->route('settings.outlets.edit', $outlet)->with('status', 'Outlet created. You can now assign your team.');
    }

    public function edit(Request $request, OutletRepository $outlets): View
    {
        $outlet = $outlets->find($request->user(), (int) $request->route('outlet'));
        return view('command-center.outlets.form', ['outlet' => $outlet, 'users' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(), 'assignments' => $outlet->userAssignments()->with('user')->get()]);
    }

    public function update(Request $request, OutletRepository $repository, OutletService $outlets): RedirectResponse
    {
        $outlets->update($repository->find($request->user(), (int) $request->route('outlet')), $request->user(), $this->validated($request, false));
        return back()->with('status', 'Outlet details saved.');
    }

    public function archive(Request $request, OutletRepository $repository, OutletService $outlets): RedirectResponse
    {
        $outlets->archive($repository->find($request->user(), (int) $request->route('outlet')), $request->user());
        return redirect()->route('settings.outlets.index')->with('status', 'Outlet archived. Historical records remain available.');
    }

    public function restore(Request $request, OutletRepository $repository, OutletService $outlets): RedirectResponse
    {
        $outlets->restore($repository->find($request->user(), (int) $request->route('outlet')), $request->user());
        return back()->with('status', 'Outlet restored.');
    }

    public function makeDefault(Request $request, OutletRepository $repository, OutletService $outlets): RedirectResponse
    {
        $outlets->makeDefault($repository->find($request->user(), (int) $request->route('outlet')), $request->user());
        return back()->with('status', 'Default outlet updated.');
    }

    public function assign(Request $request, OutletRepository $repository, OutletService $outlets): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)], 'is_default' => ['nullable', 'boolean']]);
        $outlet = $repository->find($request->user(), (int) $request->route('outlet'));
        $target = User::query()->where('company_id', $request->user()->company_id)->findOrFail($data['user_id']);
        $outlets->assign($outlet, $target, $request->user(), ['is_default' => $request->boolean('is_default')]);
        return back()->with('status', 'User assigned to this outlet.');
    }

    public function switch(Request $request, OutletAccessService $access): RedirectResponse
    {
        $data = $request->validate(['outlet_id' => ['required', 'integer']]);
        $outlet = $access->switch($request->user(), $data['outlet_id']);
        return back()->with('status', 'Working outlet switched to '.$outlet->name.'.');
    }

    public function setup(Request $request, OutletRepository $outlets): View
    {
        return view('command-center.outlets.setup', ['outlets' => $outlets->forCompany($request->user()), 'limit' => app(\App\Services\Saas\EntitlementService::class)->limit($request->user()->company, 'branches')]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating = true): array
    {
        $rules = ['name' => ['required', 'string', 'max:255'], 'legal_name' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:5000'], 'city' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', 'string', 'max:120'], 'postal_code' => ['nullable', 'string', 'max:32'], 'country' => ['nullable', 'string', 'max:120'], 'tax_number' => ['nullable', 'regex:/^[0-9A-Z]{15}$/'], 'invoice_prefix' => ['nullable', 'regex:/^[A-Z0-9-]{1,16}$/'], 'receipt_prefix' => ['nullable', 'regex:/^[A-Z0-9-]{1,24}$/'], 'timezone' => ['nullable', 'timezone']];
        if ($creating) $rules['code'] = ['required', 'regex:/^[A-Z0-9-]{2,24}$/'];
        return $request->validate($rules, ['tax_number.regex' => 'Enter a valid GSTIN format. This does not verify registration with the government.']);
    }
}

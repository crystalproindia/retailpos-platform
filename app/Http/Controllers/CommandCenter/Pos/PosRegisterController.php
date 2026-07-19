<?php

namespace App\Http\Controllers\CommandCenter\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosRegisterSession;
use App\Services\Pos\PosRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosRegisterController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.pos.registers.index', [
            'registers' => PosRegister::query()
                ->with(['branch', 'currentSession.opener'])
                ->where('company_id', $request->user()->company_id)
                ->orderBy('branch_id')
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PosRegisterService $registers): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:255'],
            'receipt_prefix' => ['nullable', 'string', 'max:24'],
        ]);
        $registers->create($request->user(), $data);

        return back()->with('status', 'POS register created.');
    }

    public function open(Request $request, PosRegisterService $registers, int $register): RedirectResponse
    {
        $data = $request->validate(['opening_cash' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $record = PosRegister::query()->where('company_id', $request->user()->company_id)->findOrFail($register);
        $registers->open($record, $request->user(), $data['opening_cash'] ?? 0, $data['notes'] ?? null);

        return back()->with('status', 'Register session opened.');
    }

    public function close(Request $request, PosRegisterService $registers, int $session): RedirectResponse
    {
        $data = $request->validate(['closing_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $record = PosRegisterSession::query()->where('company_id', $request->user()->company_id)->findOrFail($session);
        $registers->close($record, $request->user(), $data['closing_cash'], $data['notes'] ?? null);

        return back()->with('status', 'Register session closed with expected cash and variance recorded.');
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsHeaderRequest;
use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsSetting;
use App\Services\Cms\CmsHeaderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsHeaderController extends Controller
{
    public function index(Request $request): View { $company = $request->user()->company_id; return view('command-center.cms.header.index', ['definitions' => config('cms.header_settings'), 'settings' => CmsSetting::query()->where('company_id', $company)->whereIn('key', array_keys(config('cms.header_settings')))->get()->keyBy('key'), 'media' => CmsMedia::query()->where('company_id', $company)->orderBy('filename')->get()]); }
    public function update(CmsHeaderRequest $request, CmsHeaderService $service): RedirectResponse { $service->update($request->user(), $request->validated()); return back()->with('status', 'Header builder updated.'); }
}

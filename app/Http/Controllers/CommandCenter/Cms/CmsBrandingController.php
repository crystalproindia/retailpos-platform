<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsBrandingRequest;
use App\Models\Cms\CmsMedia;
use App\Repositories\Cms\CmsBrandingRepository;
use App\Services\Cms\CmsBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsBrandingController extends Controller
{
    public function index(Request $request, CmsBrandingRepository $branding): View { return view('command-center.cms.branding.index', ['definitions' => config('cms.branding_settings'), 'settings' => $branding->forCompany($request->user()->company_id), 'media' => CmsMedia::query()->where('company_id', $request->user()->company_id)->orderBy('filename')->get()]); }
    public function update(CmsBrandingRequest $request, CmsBrandingService $branding): RedirectResponse { $branding->update($request->user(), $request->validated()); return back()->with('status', 'Branding updated.'); }
}

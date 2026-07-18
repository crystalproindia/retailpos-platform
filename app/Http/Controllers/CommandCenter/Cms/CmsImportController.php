<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsContentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsImportController extends Controller
{
    public function index(): View { return view('command-center.cms.import.index'); }

    public function store(Request $request, CmsContentImportService $importer): RedirectResponse
    {
        $data = $request->validate(['manifest' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048'], 'dry_run' => ['nullable', 'boolean'], 'update_existing' => ['nullable', 'boolean'], 'publish' => ['nullable', 'boolean']]);
        $manifest = json_decode((string) file_get_contents($data['manifest']->getRealPath()), true);
        if (! is_array($manifest)) return back()->withErrors(['manifest' => 'Upload a valid JSON manifest.']);
        $result = $importer->import($request->user()->company, $request->user(), $manifest, $request->boolean('dry_run'), $request->boolean('update_existing'), $request->boolean('publish'));
        return back()->with('import_result', $result)->with('status', $request->boolean('dry_run') ? 'Manifest validated. No content was changed.' : 'Manifest imported. Review drafts before publishing.');
    }
}

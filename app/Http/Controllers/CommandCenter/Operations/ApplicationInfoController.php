<?php

namespace App\Http\Controllers\CommandCenter\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\ApplicationInfoService;
use Illuminate\View\View;

class ApplicationInfoController extends Controller
{
    public function __invoke(ApplicationInfoService $applicationInfoService): View
    {
        return view('command-center.operations.application.index', [
            'info' => $applicationInfoService->info(),
        ]);
    }
}

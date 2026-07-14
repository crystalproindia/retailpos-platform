<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicLeadIntakeRequest;
use App\Services\Crm\PublicLeadIntakeService;
use Illuminate\Http\JsonResponse;

class PublicLeadIntakeController extends Controller
{
    public function __invoke(PublicLeadIntakeRequest $request, PublicLeadIntakeService $leadIntake): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json([
                'success' => true,
                'message' => 'Lead received successfully.',
            ]);
        }

        $leadIntake->intake($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lead received successfully.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cms\PublicCmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCmsController extends Controller
{
    public function seoPage(Request $request, PublicCmsService $cms): JsonResponse { $data = $cms->seoPage((string) $request->query('path', '/')); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function landing(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->landing($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function articles(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->articles()]); }
    public function article(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->article($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function settings(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->settings()]); }
    public function sitemap(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->sitemap()]); }
    public function redirects(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->redirects()]); }
    public function robots(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->robots()]); }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cms\PublicCmsService;
use App\Services\Cms\CmsPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCmsController extends Controller
{
    public function seoPage(Request $request, PublicCmsService $cms): JsonResponse { $data = $cms->seoPage((string) $request->query('path', '/')); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function landing(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->landing($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function articles(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->articles()]); }
    public function article(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->article($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function settings(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->settings()]); }
    public function pages(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->pages()]); }
    public function page(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->pageBySlug($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function navigation(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->navigation()]); }
    public function caseStudies(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->caseStudies()]); }
    public function caseStudy(string $slug, PublicCmsService $cms): JsonResponse { $data = $cms->caseStudy($slug); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function sitemap(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->sitemap()]); }
    public function redirects(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->redirects()]); }
    public function robots(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->robots()]); }
    public function contentPages(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->contentPages()]); }
    public function contentPageByPath(Request $request, PublicCmsService $cms): JsonResponse { $data = $cms->contentPageByPath((string) $request->query('path', '/')); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function contentPage(string $pageKey, PublicCmsService $cms): JsonResponse { $data = $cms->contentPage($pageKey); abort_unless($data, 404); return response()->json(['data' => $data]); }
    public function contentNavigation(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->contentNavigation()]); }
    public function contentFooter(PublicCmsService $cms): JsonResponse { return response()->json(['data' => $cms->contentFooter()]); }
    public function previewPage(Request $request, string $slug, CmsPreviewService $previews, PublicCmsService $cms): JsonResponse { $page = $previews->resolve('page', $slug, (string) $request->query('token')); abort_unless($page, 404); return response()->json(['data' => $cms->previewPage($page)]); }
    public function previewCaseStudy(Request $request, string $slug, CmsPreviewService $previews, PublicCmsService $cms): JsonResponse { $study = $previews->resolve('case-study', $slug, (string) $request->query('token')); abort_unless($study, 404); return response()->json(['data' => $cms->previewCaseStudy($study)]); }
}

<?php

use App\Http\Controllers\Api\PublicLeadIntakeController;
use App\Http\Controllers\Api\PublicCmsController;
use Illuminate\Support\Facades\Route;

Route::post('public/leads', PublicLeadIntakeController::class)
    ->middleware(['public.lead.token', 'public.lead.payload', 'throttle:public-leads']);

Route::prefix('public/cms')->middleware('throttle:public-cms')->group(function (): void {
    Route::get('seo-page', [PublicCmsController::class, 'seoPage']);
    Route::get('landing-pages/{slug}', [PublicCmsController::class, 'landing']);
    Route::get('articles', [PublicCmsController::class, 'articles']);
    Route::get('articles/{slug}', [PublicCmsController::class, 'article']);
    Route::get('settings', [PublicCmsController::class, 'settings']);
    Route::get('pages', [PublicCmsController::class, 'pages']);
    Route::get('pages/{slug}', [PublicCmsController::class, 'page']);
    Route::get('navigation', [PublicCmsController::class, 'navigation']);
    Route::get('case-studies', [PublicCmsController::class, 'caseStudies']);
    Route::get('case-studies/{slug}', [PublicCmsController::class, 'caseStudy']);
    Route::get('sitemap', [PublicCmsController::class, 'sitemap']);
    Route::get('redirects', [PublicCmsController::class, 'redirects']);
    Route::get('robots', [PublicCmsController::class, 'robots']);
    Route::get('content/pages', [PublicCmsController::class, 'contentPages']);
    Route::get('content/page', [PublicCmsController::class, 'contentPageByPath']);
    Route::get('content/page/{pageKey}', [PublicCmsController::class, 'contentPage']);
    Route::get('content/navigation', [PublicCmsController::class, 'contentNavigation']);
    Route::get('content/footer', [PublicCmsController::class, 'contentFooter']);
});

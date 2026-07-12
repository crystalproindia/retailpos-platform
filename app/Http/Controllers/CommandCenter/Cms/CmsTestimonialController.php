<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\CmsTestimonialRequest;
use App\Repositories\Cms\CmsCaseStudyRepository;
use App\Repositories\Cms\CmsTestimonialRepository;
use App\Services\Cms\CmsTestimonialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsTestimonialController extends Controller
{
    public function index(Request $request, CmsTestimonialRepository $testimonials, CmsCaseStudyRepository $studies): View { return view('command-center.cms.testimonials.index', ['testimonials' => $testimonials->paginateForCompany($request->user()->company_id, $request->only(['search', 'trashed'])), 'caseStudies' => $studies->optionsForCompany($request->user()->company_id)]); }
    public function store(CmsTestimonialRequest $request, CmsTestimonialService $service): RedirectResponse { $service->create($request->user(), $this->payload($request->validated())); return back()->with('status', 'Testimonial created.'); }
    public function update(CmsTestimonialRequest $request, CmsTestimonialRepository $testimonials, CmsTestimonialService $service, int $testimonial): RedirectResponse { $service->update($testimonials->findForCompany($request->user()->company_id, $testimonial), $request->user(), $this->payload($request->validated())); return back()->with('status', 'Testimonial updated.'); }
    public function destroy(Request $request, CmsTestimonialRepository $testimonials, CmsTestimonialService $service, int $testimonial): RedirectResponse { $service->delete($testimonials->findForCompany($request->user()->company_id, $testimonial)); return back()->with('status', 'Testimonial moved to trash.'); }
    public function restore(Request $request, CmsTestimonialRepository $testimonials, CmsTestimonialService $service, int $testimonial): RedirectResponse { $service->restore($testimonials->findForCompany($request->user()->company_id, $testimonial, true)); return back()->with('status', 'Testimonial restored.'); }
    /** @param array<string, mixed> $data @return array<string, mixed> */ private function payload(array $data): array { foreach (['is_featured', 'show_on_homepage', 'is_active'] as $key) if (array_key_exists($key, $data)) $data[$key] = (bool) $data[$key]; return $data; }
}

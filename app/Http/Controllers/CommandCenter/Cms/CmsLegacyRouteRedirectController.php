<?php

namespace App\Http\Controllers\CommandCenter\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CmsLegacyRouteRedirectController extends Controller
{
    public function branding(): RedirectResponse
    {
        return $this->redirectToWebsiteSettings('branding', 'Branding is managed in Website Settings.');
    }

    public function header(): RedirectResponse
    {
        return $this->redirectToWebsiteSettings('header', 'Header settings are managed in Website Settings.');
    }

    public function clientLogos(): RedirectResponse
    {
        return $this->redirectToWebsiteSettings('client-logos', 'Client logo settings are ready for configuration; logo library management is coming soon.');
    }

    private function redirectToWebsiteSettings(string $fragment, string $status): RedirectResponse
    {
        return redirect()->to(route('website.settings.index').'#'.$fragment)->with('status', $status);
    }
}

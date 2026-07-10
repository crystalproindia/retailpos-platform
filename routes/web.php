<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CommandCenter\Cms\CmsDashboardController;
use App\Http\Controllers\CommandCenter\Cms\CmsHomepageController;
use App\Http\Controllers\CommandCenter\Cms\CmsMediaController;
use App\Http\Controllers\CommandCenter\Cms\CmsMenuController;
use App\Http\Controllers\CommandCenter\Cms\CmsPageController;
use App\Http\Controllers\CommandCenter\Cms\CmsSeoController;
use App\Http\Controllers\CommandCenter\Cms\CmsSettingsController as CmsAdminSettingsController;
use App\Http\Controllers\CommandCenter\DashboardController;
use App\Http\Controllers\CommandCenter\ModuleController;
use App\Http\Controllers\CommandCenter\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('modules/{module}', ModuleController::class)->name('modules.show');

    Route::middleware('role:administrator,manager')->prefix('cms')->name('cms.')->group(function (): void {
        Route::get('/', CmsDashboardController::class)->name('dashboard');

        Route::get('pages', [CmsPageController::class, 'index'])->name('pages.index');
        Route::get('pages/create', [CmsPageController::class, 'create'])->name('pages.create');
        Route::post('pages', [CmsPageController::class, 'store'])->name('pages.store');
        Route::post('pages/bulk', [CmsPageController::class, 'bulk'])->name('pages.bulk');
        Route::get('pages/{page}/edit', [CmsPageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [CmsPageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [CmsPageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/restore', [CmsPageController::class, 'restore'])->name('pages.restore');
        Route::post('pages/{page}/publish', [CmsPageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [CmsPageController::class, 'unpublish'])->name('pages.unpublish');

        Route::get('homepage', [CmsHomepageController::class, 'index'])->name('homepage.index');
        Route::put('homepage/{section}', [CmsHomepageController::class, 'update'])->name('homepage.update');

        Route::get('menus', [CmsMenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [CmsMenuController::class, 'store'])->name('menus.store');
        Route::put('menus/{menu}', [CmsMenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [CmsMenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('menus/{menu}/restore', [CmsMenuController::class, 'restore'])->name('menus.restore');
        Route::post('menus/{menu}/items', [CmsMenuController::class, 'storeItem'])->name('menus.items.store');

        Route::get('media', [CmsMediaController::class, 'index'])->name('media.index');
        Route::post('media', [CmsMediaController::class, 'store'])->name('media.store');
        Route::post('media/folders', [CmsMediaController::class, 'storeFolder'])->name('media.folders.store');
        Route::delete('media/{media}', [CmsMediaController::class, 'destroy'])->name('media.destroy');

        Route::get('settings', [CmsAdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [CmsAdminSettingsController::class, 'update'])->name('settings.update');
        Route::put('settings/footer', [CmsAdminSettingsController::class, 'updateFooter'])->name('settings.footer.update');

        Route::get('seo', [CmsSeoController::class, 'index'])->name('seo.index');
        Route::put('seo', [CmsSeoController::class, 'update'])->name('seo.update');
        Route::post('seo/redirects', [CmsSeoController::class, 'storeRedirect'])->name('seo.redirects.store');
    });

    Route::redirect('settings', 'settings/general')->name('settings.index');
    Route::middleware('role:administrator,manager')->group(function (): void {
        Route::get('settings/{section}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
    });
});

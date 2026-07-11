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
use App\Http\Controllers\CommandCenter\Crm\ActivityController;
use App\Http\Controllers\CommandCenter\Crm\ContactController;
use App\Http\Controllers\CommandCenter\Crm\CrmCompanyController;
use App\Http\Controllers\CommandCenter\Crm\CrmDashboardController;
use App\Http\Controllers\CommandCenter\Crm\FollowUpController;
use App\Http\Controllers\CommandCenter\Crm\LeadController;
use App\Http\Controllers\CommandCenter\Crm\PipelineController;
use App\Http\Controllers\CommandCenter\DashboardController;
use App\Http\Controllers\CommandCenter\ModuleController;
use App\Http\Controllers\CommandCenter\Notifications\DeliveryLogController;
use App\Http\Controllers\CommandCenter\Notifications\EventLogController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationInboxController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationPreferenceController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationTemplateController;
use App\Http\Controllers\CommandCenter\Notifications\WebhookEndpointController;
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

    Route::middleware(['role:administrator,manager,sales', 'can:crm.view'])->prefix('crm')->name('crm.')->group(function (): void {
        Route::get('/', CrmDashboardController::class)->name('dashboard');

        Route::get('leads', [LeadController::class, 'index'])->middleware('can:crm.leads.view')->name('leads.index');
        Route::get('leads/create', [LeadController::class, 'create'])->middleware('can:crm.leads.create')->name('leads.create');
        Route::post('leads', [LeadController::class, 'store'])->middleware('can:crm.leads.create')->name('leads.store');
        Route::post('leads/bulk', [LeadController::class, 'bulk'])->middleware('can:crm.leads.update')->name('leads.bulk');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->middleware('can:crm.leads.view')->name('leads.show');
        Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->middleware('can:crm.leads.update')->name('leads.edit');
        Route::put('leads/{lead}', [LeadController::class, 'update'])->middleware('can:crm.leads.update')->name('leads.update');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->middleware('can:crm.leads.delete')->name('leads.destroy');
        Route::post('leads/{lead}/restore', [LeadController::class, 'restore'])->middleware('can:crm.leads.delete')->name('leads.restore');
        Route::post('leads/{lead}/notes', [LeadController::class, 'note'])->middleware('can:crm.leads.update')->name('leads.notes.store');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('can:crm.leads.convert')->name('leads.convert');

        Route::get('companies', [CrmCompanyController::class, 'index'])->middleware('can:crm.companies.manage')->name('companies.index');
        Route::get('companies/create', [CrmCompanyController::class, 'create'])->middleware('can:crm.companies.manage')->name('companies.create');
        Route::post('companies', [CrmCompanyController::class, 'store'])->middleware('can:crm.companies.manage')->name('companies.store');
        Route::get('companies/{company}', [CrmCompanyController::class, 'show'])->middleware('can:crm.companies.manage')->name('companies.show');
        Route::get('companies/{company}/edit', [CrmCompanyController::class, 'edit'])->middleware('can:crm.companies.manage')->name('companies.edit');
        Route::put('companies/{company}', [CrmCompanyController::class, 'update'])->middleware('can:crm.companies.manage')->name('companies.update');
        Route::delete('companies/{company}', [CrmCompanyController::class, 'destroy'])->middleware('can:crm.companies.manage')->name('companies.destroy');
        Route::post('companies/{company}/restore', [CrmCompanyController::class, 'restore'])->middleware('can:crm.companies.manage')->name('companies.restore');

        Route::get('contacts', [ContactController::class, 'index'])->middleware('can:crm.contacts.manage')->name('contacts.index');
        Route::get('contacts/create', [ContactController::class, 'create'])->middleware('can:crm.contacts.manage')->name('contacts.create');
        Route::post('contacts', [ContactController::class, 'store'])->middleware('can:crm.contacts.manage')->name('contacts.store');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->middleware('can:crm.contacts.manage')->name('contacts.show');
        Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->middleware('can:crm.contacts.manage')->name('contacts.edit');
        Route::put('contacts/{contact}', [ContactController::class, 'update'])->middleware('can:crm.contacts.manage')->name('contacts.update');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->middleware('can:crm.contacts.manage')->name('contacts.destroy');
        Route::post('contacts/{contact}/restore', [ContactController::class, 'restore'])->middleware('can:crm.contacts.manage')->name('contacts.restore');

        Route::get('pipeline', [PipelineController::class, 'index'])->middleware('can:crm.pipeline.manage')->name('pipeline.index');
        Route::patch('pipeline/{lead}', [PipelineController::class, 'transition'])->middleware('can:crm.pipeline.manage')->name('pipeline.transition');

        Route::get('activities', [ActivityController::class, 'index'])->middleware('can:crm.activities.manage')->name('activities.index');
        Route::post('activities', [ActivityController::class, 'store'])->middleware('can:crm.activities.manage')->name('activities.store');
        Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->middleware('can:crm.activities.manage')->name('activities.complete');
        Route::patch('activities/{activity}/reschedule', [ActivityController::class, 'reschedule'])->middleware('can:crm.activities.manage')->name('activities.reschedule');

        Route::get('follow-ups', FollowUpController::class)->middleware('can:crm.activities.manage')->name('followups.index');
    });

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

    Route::middleware(['role:administrator,manager,sales', 'can:notifications.view'])->prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationInboxController::class, 'index'])->middleware('can:notifications.manage_own')->name('index');
        Route::post('inbox/{notification}/read', [NotificationInboxController::class, 'markRead'])->middleware('can:notifications.manage_own')->name('inbox.read');
        Route::post('inbox/{notification}/unread', [NotificationInboxController::class, 'markUnread'])->middleware('can:notifications.manage_own')->name('inbox.unread');
        Route::post('inbox/read-all', [NotificationInboxController::class, 'markAllRead'])->middleware('can:notifications.manage_own')->name('inbox.read-all');
        Route::delete('inbox/{notification}', [NotificationInboxController::class, 'destroy'])->middleware('can:notifications.manage_own')->name('inbox.destroy');

        Route::get('preferences', [NotificationPreferenceController::class, 'index'])->middleware('can:notifications.preferences.manage_own')->name('preferences.index');
        Route::put('preferences', [NotificationPreferenceController::class, 'update'])->middleware('can:notifications.preferences.manage_own')->name('preferences.update');
        Route::post('preferences/reset', [NotificationPreferenceController::class, 'reset'])->middleware('can:notifications.preferences.manage_own')->name('preferences.reset');

        Route::get('events', EventLogController::class)->middleware('can:notifications.events.view')->name('events.index');
        Route::get('deliveries', DeliveryLogController::class)->middleware('can:notifications.deliveries.view')->name('deliveries.index');

        Route::get('templates', [NotificationTemplateController::class, 'index'])->middleware('can:notifications.templates.manage')->name('templates.index');
        Route::put('templates/{template}', [NotificationTemplateController::class, 'update'])->middleware('can:notifications.templates.manage')->name('templates.update');

        Route::get('webhooks', [WebhookEndpointController::class, 'index'])->middleware('can:notifications.webhooks.view')->name('webhooks.index');
        Route::post('webhooks', [WebhookEndpointController::class, 'store'])->middleware('can:notifications.webhooks.manage')->name('webhooks.store');
        Route::put('webhooks/{webhook}', [WebhookEndpointController::class, 'update'])->middleware('can:notifications.webhooks.manage')->name('webhooks.update');
        Route::post('webhooks/{webhook}/toggle', [WebhookEndpointController::class, 'toggle'])->middleware('can:notifications.webhooks.manage')->name('webhooks.toggle');
        Route::post('webhooks/{webhook}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->middleware('can:notifications.webhooks.manage')->name('webhooks.rotate-secret');
        Route::post('webhook-deliveries/{delivery}/retry', [WebhookEndpointController::class, 'retryDelivery'])->middleware('can:notifications.webhooks.retry')->name('webhooks.deliveries.retry');
    });

    Route::redirect('settings', 'settings/general')->name('settings.index');
    Route::middleware('role:administrator,manager')->group(function (): void {
        Route::get('settings/{section}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
    });
});

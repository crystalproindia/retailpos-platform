<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CommandCenter\Cms\CmsArticleController;
use App\Http\Controllers\CommandCenter\Cms\CmsBrandingController;
use App\Http\Controllers\CommandCenter\Cms\CmsCaseStudyController;
use App\Http\Controllers\CommandCenter\Cms\CmsClientLogoController;
use App\Http\Controllers\CommandCenter\Cms\CmsContentEditorController;
use App\Http\Controllers\CommandCenter\Cms\CmsContentFooterController;
use App\Http\Controllers\CommandCenter\Cms\CmsContentNavigationController;
use App\Http\Controllers\CommandCenter\Cms\CmsCtaController;
use App\Http\Controllers\CommandCenter\Cms\CmsDashboardController;
use App\Http\Controllers\CommandCenter\Cms\CmsFaqController;
use App\Http\Controllers\CommandCenter\Cms\CmsFooterBuilderController;
use App\Http\Controllers\CommandCenter\Cms\CmsHeaderController;
use App\Http\Controllers\CommandCenter\Cms\CmsHomepageController;
use App\Http\Controllers\CommandCenter\Cms\CmsImportController;
use App\Http\Controllers\CommandCenter\Cms\CmsLandingPageController;
use App\Http\Controllers\CommandCenter\Cms\CmsLegacyRouteRedirectController;
use App\Http\Controllers\CommandCenter\Cms\CmsMediaController;
use App\Http\Controllers\CommandCenter\Cms\CmsMenuController;
use App\Http\Controllers\CommandCenter\Cms\CmsPageController;
use App\Http\Controllers\CommandCenter\Cms\CmsRedirectController;
use App\Http\Controllers\CommandCenter\Cms\CmsSeoController;
use App\Http\Controllers\CommandCenter\Cms\CmsSeoPageController;
use App\Http\Controllers\CommandCenter\Cms\CmsSettingsController as CmsAdminSettingsController;
use App\Http\Controllers\CommandCenter\Cms\CmsTestimonialController;
use App\Http\Controllers\CommandCenter\Cms\CmsThemeController;
use App\Http\Controllers\CommandCenter\Cms\CmsTrustMetricController;
use App\Http\Controllers\CommandCenter\Compliance\GstSettingsController;
use App\Http\Controllers\CommandCenter\Compliance\GstNoteController;
use App\Http\Controllers\CommandCenter\Compliance\GstComplianceController;
use App\Http\Controllers\CommandCenter\Crm\ActivityController;
use App\Http\Controllers\CommandCenter\Crm\AiLeadAssistantController;
use App\Http\Controllers\CommandCenter\Crm\ContactController;
use App\Http\Controllers\CommandCenter\Crm\CrmCompanyController;
use App\Http\Controllers\CommandCenter\Crm\CrmCustomerController;
use App\Http\Controllers\CommandCenter\Crm\CrmDashboardController;
use App\Http\Controllers\CommandCenter\Crm\CrmOnboardingController;
use App\Http\Controllers\CommandCenter\Crm\CrmReportController;
use App\Http\Controllers\CommandCenter\Crm\CrmSupportTicketController;
use App\Http\Controllers\CommandCenter\Crm\CustomerPortalAccessController as CrmCustomerPortalAccessController;
use App\Http\Controllers\CommandCenter\Crm\DemoGoogleCalendarSyncController;
use App\Http\Controllers\CommandCenter\Crm\DemoScheduleController;
use App\Http\Controllers\CommandCenter\Crm\FollowUpController;
use App\Http\Controllers\CommandCenter\Crm\LeadController;
use App\Http\Controllers\CommandCenter\Crm\InvoiceController;
use App\Http\Controllers\CommandCenter\Crm\OpportunityController;
use App\Http\Controllers\CommandCenter\Crm\PipelineController;
use App\Http\Controllers\CommandCenter\Crm\ProformaController;
use App\Http\Controllers\CommandCenter\Crm\ProformaShareController;
use App\Http\Controllers\CommandCenter\Crm\QuotationController;
use App\Http\Controllers\CommandCenter\Crm\QuotationShareController;
use App\Http\Controllers\CommandCenter\Customers\CustomerController;
use App\Http\Controllers\CommandCenter\Customers\CustomerDashboardController;
use App\Http\Controllers\CommandCenter\Customers\CustomerGroupController;
use App\Http\Controllers\CommandCenter\Customers\CustomerIntelligenceController;
use App\Http\Controllers\CommandCenter\Customers\CustomerLoyaltyController;
use App\Http\Controllers\CommandCenter\Customers\CustomerSettingsController;
use App\Http\Controllers\CommandCenter\Customers\CustomerWalletController;
use App\Http\Controllers\CommandCenter\DashboardController;
use App\Http\Controllers\CommandCenter\Integrations\EmailDeliveryLogController;
use App\Http\Controllers\CommandCenter\Integrations\EmailIntegrationController;
use App\Http\Controllers\CommandCenter\Integrations\GoogleCalendarIntegrationController;
use App\Http\Controllers\CommandCenter\Inventory\BarcodeLabelTemplateController;
use App\Http\Controllers\CommandCenter\Inventory\BarcodePrintBatchController;
use App\Http\Controllers\CommandCenter\Inventory\ChannelProductMappingController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryBrandController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryCategoryController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryDashboardController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryDecisionDashboardController;
use App\Http\Controllers\CommandCenter\Inventory\InventorySettingsController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryTaxRateController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryUnitController;
use App\Http\Controllers\CommandCenter\Inventory\OpeningStockController;
use App\Http\Controllers\CommandCenter\Inventory\ProductController;
use App\Http\Controllers\CommandCenter\Inventory\ReorderSuggestionController;
use App\Http\Controllers\CommandCenter\Inventory\SalesChannelController;
use App\Http\Controllers\CommandCenter\Inventory\StockAdjustmentController;
use App\Http\Controllers\CommandCenter\Inventory\StockLedgerController;
use App\Http\Controllers\CommandCenter\Inventory\StockLocationController;
use App\Http\Controllers\CommandCenter\Inventory\WarehouseController;
use App\Http\Controllers\CommandCenter\ModuleController;
use App\Http\Controllers\CommandCenter\Notifications\DeliveryLogController;
use App\Http\Controllers\CommandCenter\Notifications\EventLogController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationInboxController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationPreferenceController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationTemplateController;
use App\Http\Controllers\CommandCenter\Notifications\WebhookEndpointController;
use App\Http\Controllers\CommandCenter\Operations\ApplicationInfoController;
use App\Http\Controllers\CommandCenter\Operations\FailedJobController;
use App\Http\Controllers\CommandCenter\Operations\HealthCheckController;
use App\Http\Controllers\CommandCenter\Operations\OperationsDashboardController;
use App\Http\Controllers\CommandCenter\Operations\QueueMonitorController;
use App\Http\Controllers\CommandCenter\Operations\ScheduleMonitorController;
use App\Http\Controllers\CommandCenter\Pos\PosController;
use App\Http\Controllers\CommandCenter\Pos\PosRegisterController;
use App\Http\Controllers\CommandCenter\Pos\PosOfflineController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionCampaignController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionCouponController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionDashboardController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionRuleController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionSettingsController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionSimulatorController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionUsageController;
use App\Http\Controllers\CommandCenter\Purchases\GoodsReceiptController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseDashboardController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseOrderController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseRequestController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseReturnController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseSettingsController;
use App\Http\Controllers\CommandCenter\Purchases\SupplierController;
use App\Http\Controllers\CommandCenter\Purchases\SupplierDashboardController;
use App\Http\Controllers\CommandCenter\SettingsController;
use App\Http\Controllers\Portal\CustomerPortalAccessController;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\PublicProformaController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PublicInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::prefix('q/{publicToken}')->middleware('throttle:public-quotation')->group(function (): void {
    Route::get('/', [PublicQuotationController::class, 'show'])->name('quotations.public.show');
    Route::get('pdf', [PublicQuotationController::class, 'pdf'])->name('quotations.public.pdf');
    Route::post('decision', [PublicQuotationController::class, 'respond'])->name('quotations.public.decision');
});
Route::prefix('i/{token}')->middleware('throttle:public-invoice')->group(function (): void {
    Route::get('/', [PublicInvoiceController::class, 'show'])->name('invoices.public.show');
    Route::get('pdf', [PublicInvoiceController::class, 'pdf'])->name('invoices.public.pdf');
    Route::get('receipts/{payment}', [PublicInvoiceController::class, 'receipt'])->whereNumber('payment')->name('invoices.public.receipts.pdf');
});
Route::get('pi/{publicToken}', [PublicProformaController::class, 'show'])->name('proformas.public.show');

Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::middleware(['portal.guest'])->group(function (): void {
        Route::get('login', [CustomerPortalAccessController::class, 'login'])->name('login');
        Route::get('access/{token}', [CustomerPortalAccessController::class, 'access'])->middleware('throttle:portal-access')->name('access');
    });

    Route::middleware('portal.auth')->group(function (): void {
        Route::post('logout', [CustomerPortalAccessController::class, 'logout'])->name('logout');
        Route::get('/', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::get('quotations', [CustomerPortalController::class, 'quotations'])->name('quotations.index');
        Route::get('quotations/{quotation}', [CustomerPortalController::class, 'quotation'])->whereNumber('quotation')->name('quotations.show');
        Route::get('proformas', [CustomerPortalController::class, 'proformas'])->name('proformas.index');
        Route::get('proformas/{proforma}', [CustomerPortalController::class, 'proforma'])->whereNumber('proforma')->name('proformas.show');
        Route::get('onboarding', [CustomerPortalController::class, 'onboardings'])->name('onboarding.index');
        Route::get('onboarding/{onboarding}', [CustomerPortalController::class, 'onboarding'])->whereNumber('onboarding')->name('onboarding.show');
        Route::get('support', [CustomerPortalController::class, 'support'])->name('support.index');
        Route::get('support/create', [CustomerPortalController::class, 'createSupport'])->name('support.create');
        Route::post('support', [CustomerPortalController::class, 'storeSupport'])->middleware('throttle:portal-support')->name('support.store');
        Route::get('support/{ticket}', [CustomerPortalController::class, 'showSupport'])->whereNumber('ticket')->name('support.show');
        Route::post('support/{ticket}/replies', [CustomerPortalController::class, 'replySupport'])->whereNumber('ticket')->middleware('throttle:portal-support')->name('support.replies.store');
        Route::get('services', [CustomerPortalController::class, 'services'])->name('services');
        Route::get('services/request', [CustomerPortalController::class, 'createServiceRequest'])->name('services.request');
        Route::post('services/request', [CustomerPortalController::class, 'storeServiceRequest'])->middleware('throttle:portal-service-requests')->name('services.request.store');
        Route::get('profile', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::put('profile', [CustomerPortalController::class, 'updateProfile'])->name('profile.update');
    });
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

    Route::prefix('integrations/google')->name('integrations.google.')->group(function (): void {
        Route::get('/', [GoogleCalendarIntegrationController::class, 'index'])->middleware('can:integrations.google.view')->name('index');
        Route::get('connect', [GoogleCalendarIntegrationController::class, 'connect'])->middleware('can:integrations.google.connect')->name('connect');
        Route::get('callback', [GoogleCalendarIntegrationController::class, 'callback'])->middleware('can:integrations.google.connect')->name('callback');
        Route::post('disconnect', [GoogleCalendarIntegrationController::class, 'disconnect'])->middleware('can:integrations.google.disconnect')->name('disconnect');
        Route::post('test', [GoogleCalendarIntegrationController::class, 'test'])->middleware('can:integrations.google.connect')->name('test');
        Route::put('settings', [GoogleCalendarIntegrationController::class, 'updateSettings'])->middleware('can:integrations.google.connect')->name('settings.update');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('integrations/email', [EmailIntegrationController::class, 'index'])->middleware('can:integrations.email.view')->name('integrations.email.index');
        Route::put('integrations/email', [EmailIntegrationController::class, 'update'])->middleware('can:integrations.email.manage')->name('integrations.email.update');
        Route::post('integrations/email/test', [EmailIntegrationController::class, 'test'])->middleware(['can:email.test.send', 'throttle:email-test'])->name('integrations.email.test');
        Route::post('integrations/email/disable', [EmailIntegrationController::class, 'disable'])->middleware('can:integrations.email.manage')->name('integrations.email.disable');
        Route::delete('integrations/email/password', [EmailIntegrationController::class, 'removePassword'])->middleware('can:integrations.email.manage')->name('integrations.email.password.destroy');
        Route::get('email-deliveries', [EmailDeliveryLogController::class, 'index'])->middleware('can:email.deliveries.view')->name('email-deliveries.index');
        Route::post('email-deliveries/{emailDelivery}/retry', [EmailDeliveryLogController::class, 'retry'])->middleware('can:email.deliveries.retry')->name('email-deliveries.retry');
        Route::post('email-deliveries/{emailDelivery}/cancel', [EmailDeliveryLogController::class, 'cancel'])->middleware('can:email.deliveries.retry')->name('email-deliveries.cancel');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:crm.view'])->prefix('crm')->name('crm.')->group(function (): void {
        Route::get('/', CrmDashboardController::class)->name('dashboard');
        Route::get('reports', [CrmReportController::class, 'index'])->middleware('can:crm.reports.view')->name('reports.index');
        Route::get('reports/executive', [CrmReportController::class, 'executive'])->middleware('can:crm.reports.executive')->name('reports.executive');
        Route::get('reports/visualization', [CrmReportController::class, 'visualization'])->middleware('can:crm.reports.view')->name('reports.visualization');
        Route::get('reports/{report}', [CrmReportController::class, 'show'])->whereIn('report', ['sales', 'payments', 'onboarding', 'support', 'customers'])->middleware('can:crm.reports.view')->name('reports.show');
        Route::get('reports/{report}/export', [CrmReportController::class, 'export'])->whereIn('report', ['sales', 'payments', 'support'])->middleware('can:crm.reports.export')->name('reports.export');

        Route::get('leads', [LeadController::class, 'index'])->middleware('can:crm.leads.view')->name('leads.index');
        Route::get('demo-requests', [LeadController::class, 'demoRequests'])->middleware('can:crm.leads.view')->name('demo-requests.index');
        Route::get('leads/create', [LeadController::class, 'create'])->middleware('can:crm.leads.create')->name('leads.create');
        Route::post('leads', [LeadController::class, 'store'])->middleware('can:crm.leads.create')->name('leads.store');
        Route::post('leads/bulk', [LeadController::class, 'bulk'])->middleware('can:crm.leads.update')->name('leads.bulk');
        Route::post('leads/{lead}/ai/analyze', [AiLeadAssistantController::class, 'analyze'])->middleware('can:crm.ai.refresh_score')->name('leads.ai.analyze');
        Route::post('leads/{lead}/ai/follow-up', [AiLeadAssistantController::class, 'generate'])->middleware('can:crm.ai.generate')->name('leads.ai.follow-up');
        Route::get('leads/{lead}/demos/create', [DemoScheduleController::class, 'create'])->middleware('can:crm.demos.create')->name('demos.create');
        Route::post('leads/{lead}/demos', [DemoScheduleController::class, 'store'])->middleware('can:crm.demos.create')->name('demos.store');
        Route::get('demos/{demo}/reschedule', [DemoScheduleController::class, 'edit'])->middleware('can:crm.demos.update')->name('demos.edit');
        Route::patch('demos/{demo}/reschedule', [DemoScheduleController::class, 'reschedule'])->middleware('can:crm.demos.update')->name('demos.reschedule');
        Route::post('demos/{demo}/complete', [DemoScheduleController::class, 'complete'])->middleware('can:crm.demos.complete')->name('demos.complete');
        Route::post('demos/{demo}/cancel', [DemoScheduleController::class, 'cancel'])->middleware('can:crm.demos.cancel')->name('demos.cancel');
        Route::post('demos/{demo}/sync-google-calendar', DemoGoogleCalendarSyncController::class)->middleware('can:crm.demos.sync_calendar')->name('demos.sync-google-calendar');
        Route::get('quotations', [QuotationController::class, 'index'])->middleware('can:crm.quotations.view')->name('quotations.index');
        Route::get('leads/{lead}/quotations/create', [QuotationController::class, 'create'])->middleware('can:crm.quotations.create')->name('quotations.create');
        Route::post('leads/{lead}/quotations', [QuotationController::class, 'store'])->middleware('can:crm.quotations.create')->name('quotations.store');
        Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->middleware('can:crm.quotations.update')->name('quotations.edit');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->middleware('can:crm.quotations.update')->name('quotations.update');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->middleware('can:crm.quotations.send')->name('quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->middleware('can:crm.quotations.accept')->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->middleware('can:crm.quotations.reject')->name('quotations.reject');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->middleware('can:crm.quotations.update')->name('quotations.convert');
        Route::post('quotations/{quotation}/public-link', [QuotationController::class, 'publicLink'])->middleware('can:crm.quotations.update')->name('quotations.public-link');
        Route::post('quotations/{quotation}/revision', [QuotationController::class, 'revision'])->middleware('can:crm.quotations.update')->name('quotations.revision');
        Route::get('quotations/{quotation}/pdf', [QuotationShareController::class, 'downloadPdf'])->middleware('can:crm.quotations.view')->name('quotations.pdf.download');
        Route::get('quotations/{quotation}/pdf/preview', [QuotationShareController::class, 'previewPdf'])->middleware('can:crm.quotations.view')->name('quotations.pdf.preview');
        Route::get('quotations/{quotation}/email/create', [QuotationShareController::class, 'createEmail'])->middleware('can:crm.quotations.send')->name('quotations.email.create');
        Route::post('quotations/{quotation}/email/send', [QuotationShareController::class, 'sendEmail'])->middleware('can:crm.quotations.send')->name('quotations.email.send');
        Route::get('quotations/{quotation}/whatsapp', [QuotationShareController::class, 'whatsapp'])->middleware('can:crm.quotations.send')->name('quotations.whatsapp');
        Route::get('proforma-invoices', [ProformaController::class, 'index'])->middleware('can:crm.proformas.view')->name('proformas.index');
        Route::get('quotations/{quotation}/proforma/create', [ProformaController::class, 'createFromQuotation'])->middleware('can:crm.proformas.create')->name('proformas.create-from-quotation');
        Route::get('customers/{customer}/proforma/create', [ProformaController::class, 'createFromCustomer'])->middleware('can:crm.proformas.create')->name('proformas.create-from-customer');
        Route::post('proforma-invoices', [ProformaController::class, 'store'])->middleware('can:crm.proformas.create')->name('proformas.store');
        Route::get('proforma-invoices/{proforma}/pdf', [ProformaController::class, 'pdf'])->middleware('can:crm.proformas.view')->name('proformas.pdf');
        Route::get('proforma-invoices/{proforma}/pdf/preview', [ProformaController::class, 'preview'])->middleware('can:crm.proformas.view')->name('proformas.pdf.preview');
        Route::post('proforma-invoices/{proforma}/payments', [ProformaController::class, 'payment'])->middleware('can:crm.proformas.record_payment')->name('proformas.payments.store');
        Route::post('proforma-invoices/{proforma}/mark-sent', [ProformaController::class, 'sent'])->middleware('can:crm.proformas.send')->name('proformas.mark-sent');
        Route::post('proforma-invoices/{proforma}/sent', [ProformaController::class, 'sent'])->middleware('can:crm.proformas.send')->name('proformas.sent');
        Route::post('proforma-invoices/{proforma}/public-link', [ProformaController::class, 'link'])->middleware('can:crm.proformas.send')->name('proformas.public-link');
        Route::get('proforma-invoices/{proforma}/email/create', [ProformaShareController::class, 'createEmail'])->middleware('can:crm.proformas.send')->name('proformas.email.create');
        Route::post('proforma-invoices/{proforma}/email/send', [ProformaShareController::class, 'sendEmail'])->middleware('can:crm.proformas.send')->name('proformas.email.send');
        Route::get('proforma-invoices/{proforma}/whatsapp', [ProformaShareController::class, 'whatsapp'])->middleware('can:crm.proformas.send')->name('proformas.whatsapp');
        Route::get('proforma-invoices/{proforma}', [ProformaController::class, 'show'])->middleware('can:crm.proformas.view')->name('proformas.show');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->middleware('can:crm.quotations.view')->name('quotations.show');
        Route::get('customers', [CrmCustomerController::class, 'index'])->middleware('can:crm.customers.view')->name('customers.index');
        Route::get('customers/{customer}', [CrmCustomerController::class, 'show'])->middleware('can:crm.customers.view')->name('customers.show');
        Route::post('customers/{customer}/portal-users', [CrmCustomerPortalAccessController::class, 'invite'])->middleware('can:crm.customers.portal.manage')->name('customers.portal-users.invite');
        Route::post('customers/{customer}/portal-users/{portalUser}/link', [CrmCustomerPortalAccessController::class, 'refresh'])->middleware('can:crm.customers.portal.manage')->name('customers.portal-users.link');
        Route::patch('customers/{customer}/portal-users/{portalUser}/status', [CrmCustomerPortalAccessController::class, 'status'])->middleware('can:crm.customers.portal.manage')->name('customers.portal-users.status');
        Route::post('customers/{customer}/onboarding', [CrmOnboardingController::class, 'startFromCustomer'])->middleware('can:crm.onboarding.create')->name('customers.onboarding.start');
        Route::get('leads/{lead}/customer-conversion', [CrmCustomerController::class, 'createForLead'])->middleware('can:crm.customers.convert')->name('customers.create-for-lead');
        Route::post('leads/{lead}/customer-conversion', [CrmCustomerController::class, 'storeForLead'])->middleware('can:crm.customers.convert')->name('customers.store-for-lead');
        Route::get('quotations/{quotation}/customer-conversion', [CrmCustomerController::class, 'createForQuotation'])->middleware('can:crm.customers.convert')->name('customers.create-for-quotation');
        Route::post('quotations/{quotation}/customer-conversion', [CrmCustomerController::class, 'storeForQuotation'])->middleware('can:crm.customers.convert')->name('customers.store-for-quotation');
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
        Route::post('pipeline/cards/{lead}/move', [PipelineController::class, 'move'])->middleware('can:crm.pipeline.manage')->name('pipeline.cards.move');
        Route::patch('pipeline/{lead}', [PipelineController::class, 'transition'])->middleware('can:crm.pipeline.manage')->name('pipeline.transition');

        Route::get('onboarding', [CrmOnboardingController::class, 'index'])->middleware('can:crm.onboarding.view')->name('onboarding.index');
        Route::get('onboarding/{onboarding}', [CrmOnboardingController::class, 'show'])->middleware('can:crm.onboarding.view')->name('onboarding.show');
        Route::get('onboarding/{onboarding}/edit', [CrmOnboardingController::class, 'edit'])->middleware('can:crm.onboarding.update')->name('onboarding.edit');
        Route::put('onboarding/{onboarding}', [CrmOnboardingController::class, 'update'])->middleware('can:crm.onboarding.update')->name('onboarding.update');
        Route::post('onboarding/{onboarding}/status', [CrmOnboardingController::class, 'status'])->middleware('can:crm.onboarding.update')->name('onboarding.status');
        Route::post('onboarding/{onboarding}/tasks', [CrmOnboardingController::class, 'storeTask'])->middleware('can:crm.onboarding.update')->name('onboarding.tasks.store');
        Route::post('onboarding/{onboarding}/tasks/{task}', [CrmOnboardingController::class, 'task'])->middleware('can:crm.onboarding.complete_task')->name('onboarding.tasks.update');
        Route::post('onboarding/{onboarding}/notes', [CrmOnboardingController::class, 'note'])->middleware('can:crm.onboarding.update')->name('onboarding.notes.store');
        Route::post('onboarding/{onboarding}/documents', [CrmOnboardingController::class, 'document'])->middleware('can:crm.onboarding.manage_documents')->name('onboarding.documents.store');
        Route::put('onboarding/{onboarding}/documents/{document}', [CrmOnboardingController::class, 'updateDocument'])->middleware('can:crm.onboarding.manage_documents')->name('onboarding.documents.update');
        Route::post('proforma-invoices/{proforma}/onboarding', [CrmOnboardingController::class, 'startFromProforma'])->middleware('can:crm.onboarding.create')->name('proformas.onboarding.start');

        Route::get('support', fn () => redirect()->route('crm.support.tickets.index'))->middleware('can:crm.support.view')->name('support.index');
        Route::get('support/tickets', [CrmSupportTicketController::class, 'index'])->middleware('can:crm.support.view')->name('support.tickets.index');
        Route::get('support/tickets/create', [CrmSupportTicketController::class, 'create'])->middleware('can:crm.support.create')->name('support.tickets.create');
        Route::post('support/tickets', [CrmSupportTicketController::class, 'store'])->middleware('can:crm.support.create')->name('support.tickets.store');
        Route::get('support/tickets/{ticket}', [CrmSupportTicketController::class, 'show'])->middleware('can:crm.support.view')->name('support.tickets.show');
        Route::put('support/tickets/{ticket}', [CrmSupportTicketController::class, 'update'])->middleware('can:crm.support.update')->name('support.tickets.update');
        Route::post('support/tickets/{ticket}/messages', [CrmSupportTicketController::class, 'message'])->middleware('can:crm.support.reply')->name('support.tickets.messages.store');
        Route::post('support/tickets/{ticket}/attachments', [CrmSupportTicketController::class, 'attachment'])->middleware('can:crm.support.update')->name('support.tickets.attachments.store');

        Route::get('activities', [ActivityController::class, 'index'])->middleware('can:crm.activities.manage')->name('activities.index');
        Route::post('activities', [ActivityController::class, 'store'])->middleware('can:crm.activities.manage')->name('activities.store');
        Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->middleware('can:crm.activities.manage')->name('activities.complete');
        Route::patch('activities/{activity}/reschedule', [ActivityController::class, 'reschedule'])->middleware('can:crm.activities.manage')->name('activities.reschedule');
        Route::post('activities/{activity}/cancel', [ActivityController::class, 'cancel'])->middleware('can:sales.followups.manage')->name('activities.cancel');

        Route::get('follow-ups', FollowUpController::class)->middleware('can:crm.activities.manage')->name('followups.index');
    });

    Route::middleware(['role:administrator,manager,sales'])->prefix('sales')->name('sales.')->group(function (): void {
        Route::get('pipeline', fn () => redirect()->route('crm.pipeline.index'))->middleware('can:sales.pipeline.view')->name('pipeline.index');
        Route::get('follow-ups', fn () => redirect()->route('crm.followups.index'))->middleware('can:sales.followups.view')->name('followups.index');
        Route::get('quotations', fn () => redirect()->route('crm.quotations.index'))->middleware('can:sales.quotations.view')->name('quotations.index');
        Route::get('leads/{lead}/opportunities/create', [OpportunityController::class, 'create'])->middleware('can:sales.opportunities.create')->name('opportunities.create');
        Route::post('leads/{lead}/opportunities', [OpportunityController::class, 'store'])->middleware('can:sales.opportunities.create')->name('opportunities.store');
        Route::get('opportunities', [OpportunityController::class, 'index'])->middleware('can:sales.opportunities.view')->name('opportunities.index');
        Route::post('opportunities/{opportunity}/move', [OpportunityController::class, 'move'])->middleware('can:sales.opportunities.update')->name('opportunities.move');
        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:sales.invoices.view')->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->middleware('can:sales.invoices.create')->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware('can:sales.invoices.create')->name('invoices.store');
        Route::get('invoices/export', [InvoiceController::class, 'export'])->middleware('can:sales.finance.export')->name('invoices.export');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('can:sales.invoices.update')->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('can:sales.invoices.update')->name('invoices.update');
        Route::get('quotations/{quotation}/invoices/create', [InvoiceController::class, 'createFromQuotation'])->middleware('can:sales.invoices.create')->name('invoices.create-from-quotation');
        Route::post('quotations/{quotation}/invoices', [InvoiceController::class, 'storeFromQuotation'])->middleware('can:sales.invoices.create')->name('invoices.store-from-quotation');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('can:sales.invoices.view')->name('invoices.show');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('can:sales.invoices.issue')->name('invoices.issue');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->middleware('can:sales.payments.record')->name('invoices.payments.store');
        Route::post('invoices/{invoice}/payments/{payment}/clear', [InvoiceController::class, 'clear'])->middleware('can:sales.payments.clear')->name('invoices.payments.clear');
        Route::post('invoices/{invoice}/payments/{payment}/reverse', [InvoiceController::class, 'reverse'])->middleware('can:sales.payments.reverse')->name('invoices.payments.reverse');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('can:sales.invoices.cancel')->name('invoices.cancel');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('can:sales.invoices.pdf')->name('invoices.pdf');
        Route::get('invoices/{invoice}/receipts/{payment}', [InvoiceController::class, 'receipt'])->middleware('can:sales.receipts.pdf')->name('invoices.receipts.pdf');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('can:sales.invoices.send')->name('invoices.send');
        Route::get('invoices/{invoice}/whatsapp', [InvoiceController::class, 'whatsapp'])->middleware('can:sales.invoices.send')->name('invoices.whatsapp');
        Route::post('invoices/{invoice}/reminder', [InvoiceController::class, 'reminder'])->middleware('can:sales.reminders.send')->name('invoices.reminder');
        Route::post('invoices/{invoice}/public-link/revoke', [InvoiceController::class, 'revokeLink'])->middleware('can:sales.invoices.public_link')->name('invoices.public-link.revoke');
        Route::post('invoices/{invoice}/payments/{payment}/receipt/send', [InvoiceController::class, 'sendReceipt'])->middleware('can:sales.receipts.send')->name('invoices.receipts.send');
        Route::get('invoices/{invoice}/payments/{payment}/receipt/whatsapp', [InvoiceController::class, 'receiptWhatsapp'])->middleware('can:sales.receipts.send')->name('invoices.receipts.whatsapp');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:customers.view'])->prefix('customers')->name('customers.')->group(function (): void {
        Route::get('/', CustomerDashboardController::class)->middleware('can:customers.dashboard.view')->name('dashboard');
        Route::get('directory', [CustomerController::class, 'index'])->name('index');
        Route::get('create', [CustomerController::class, 'create'])->middleware('can:customers.create')->name('create');
        Route::post('/', [CustomerController::class, 'store'])->middleware('can:customers.create')->name('store');
        Route::get('{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('show');
        Route::get('{customer}/edit', [CustomerController::class, 'edit'])->whereNumber('customer')->middleware('can:customers.update')->name('edit');
        Route::put('{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->middleware('can:customers.update')->name('update');
        Route::delete('{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer')->middleware('can:customers.delete')->name('destroy');
        Route::post('{customer}/restore', [CustomerController::class, 'restore'])->whereNumber('customer')->middleware('can:customers.restore')->name('restore');
        Route::post('{customer}/addresses', [CustomerController::class, 'storeAddress'])->whereNumber('customer')->middleware('can:customers.update')->name('addresses.store');
        Route::post('{customer}/contacts', [CustomerController::class, 'storeContact'])->whereNumber('customer')->middleware('can:customers.update')->name('contacts.store');
        Route::post('{customer}/groups', [CustomerGroupController::class, 'assign'])->whereNumber('customer')->middleware('can:customers.groups.manage')->name('groups.assign');
        Route::post('{customer}/loyalty-adjustments', [CustomerLoyaltyController::class, 'adjust'])->whereNumber('customer')->middleware('can:customers.loyalty.adjust')->name('loyalty.adjust');
        Route::post('{customer}/wallet-adjustments', [CustomerWalletController::class, 'adjust'])->whereNumber('customer')->middleware('can:customers.wallet.adjust')->name('wallet.adjust');
        Route::get('groups/manage', [CustomerGroupController::class, 'index'])->middleware('can:customers.groups.view')->name('groups.index');
        Route::post('groups/manage', [CustomerGroupController::class, 'store'])->middleware('can:customers.groups.manage')->name('groups.store');
        Route::put('groups/manage/{group}', [CustomerGroupController::class, 'update'])->middleware('can:customers.groups.manage')->name('groups.update');
        Route::delete('groups/manage/{group}', [CustomerGroupController::class, 'destroy'])->middleware('can:customers.groups.manage')->name('groups.destroy');
        Route::post('groups/manage/{group}/restore', [CustomerGroupController::class, 'restore'])->middleware('can:customers.groups.manage')->name('groups.restore');
        Route::get('birthdays/upcoming', [CustomerIntelligenceController::class, 'birthdays'])->middleware('can:customers.birthdays.view')->name('birthdays.index');
        Route::get('inactive/list', [CustomerIntelligenceController::class, 'inactive'])->middleware('can:customers.inactive.view')->name('inactive.index');
        Route::get('lost/list', [CustomerIntelligenceController::class, 'lost'])->middleware('can:customers.lost.view')->name('lost.index');
        Route::get('returns/frequent', [CustomerIntelligenceController::class, 'returns'])->middleware('can:customers.returns.view')->name('returns.index');
        Route::get('insights', [CustomerIntelligenceController::class, 'insights'])->middleware('can:customers.insights.view')->name('insights.index');
        Route::post('insights/refresh', [CustomerIntelligenceController::class, 'refresh'])->middleware('can:customers.insights.view')->name('insights.refresh');
        Route::get('settings', [CustomerSettingsController::class, 'index'])->middleware('can:customers.settings.manage')->name('settings.index');
        Route::put('settings', [CustomerSettingsController::class, 'update'])->middleware('can:customers.settings.manage')->name('settings.update');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:pos.view'])->prefix('pos')->name('pos.')->group(function (): void {
        Route::get('registers', [PosRegisterController::class, 'index'])->middleware('can:pos.registers.view')->name('registers.index');
        Route::post('registers', [PosRegisterController::class, 'store'])->middleware('can:pos.registers.manage')->name('registers.store');
        Route::post('registers/{register}/open', [PosRegisterController::class, 'open'])->whereNumber('register')->middleware('can:pos.sessions.open')->name('registers.open');
        Route::post('register-sessions/{session}/close', [PosRegisterController::class, 'close'])->whereNumber('session')->middleware('can:pos.sessions.close')->name('registers.sessions.close');
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('dashboard', [PosController::class, 'dashboard'])->name('dashboard');
        Route::get('terminal', [PosController::class, 'terminal'])->name('terminal');
        Route::get('mobile', [PosController::class, 'mobile'])->name('mobile');
        Route::prefix('offline')->name('offline.')->group(function (): void {
            Route::get('bootstrap', [PosOfflineController::class, 'bootstrap'])->middleware('can:pos.offline.use')->name('bootstrap');
            Route::post('sync', [PosOfflineController::class, 'sync'])->middleware('can:pos.offline.sync')->name('sync');
            Route::get('status', [PosOfflineController::class, 'status'])->middleware('can:pos.offline.use')->name('status');
            Route::get('/', [PosOfflineController::class, 'index'])->middleware(['role:administrator,manager', 'can:pos.offline.monitor'])->name('index');
            Route::get('records', [PosOfflineController::class, 'records'])->middleware('can:pos.offline.sync')->name('records');
            Route::post('records/{record}/retry', [PosOfflineController::class, 'retry'])->whereNumber('record')->middleware(['role:administrator,manager', 'can:pos.offline.retry'])->name('records.retry');
        });
        Route::get('held', [PosController::class, 'heldBills'])->middleware('can:pos.hold')->name('held.index');
        Route::get('sales', [PosController::class, 'salesHistory'])->middleware('can:pos.sales.view')->name('sales.index');
        Route::get('catalog', [PosController::class, 'catalog'])->name('catalog');
        Route::get('customers/lookup', [PosController::class, 'customer'])->name('customers.lookup');
        Route::post('customers/quick-create', [PosController::class, 'quickCustomer'])->middleware('can:pos.customers.create')->name('customers.quick-create');
        Route::post('hold', [PosController::class, 'hold'])->middleware('can:pos.hold')->name('hold');
        Route::post('checkout', [PosController::class, 'complete'])->middleware('can:pos.checkout')->name('checkout');
        Route::get('held/{sale}', [PosController::class, 'resume'])->whereNumber('sale')->middleware('can:pos.hold')->name('held.resume');
        Route::get('receipts/{sale}', [PosController::class, 'receipt'])->whereNumber('sale')->name('receipts.show');
        Route::get('receipts/{sale}/pdf', [PosController::class, 'receiptPdf'])->whereNumber('sale')->middleware('can:pos.receipts.view')->name('receipts.pdf');
        Route::post('sales/{sale}/void', [PosController::class, 'void'])->whereNumber('sale')->middleware('can:pos.sales.void')->name('sales.void');
    });

    Route::middleware(['role:administrator,manager', 'can:compliance.gst.view'])->prefix('compliance/gst')->name('compliance.gst.')->group(function (): void {
        Route::get('/', [GstComplianceController::class, 'dashboard'])->name('dashboard');
        Route::get('settings', [GstSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [GstSettingsController::class, 'update'])->middleware('can:compliance.gst.settings.manage')->name('settings.update');
        Route::get('notes', [GstNoteController::class, 'index'])->middleware('can:compliance.credit_notes.view')->name('notes.index');
        Route::post('notes', [GstNoteController::class, 'store'])->middleware('can:compliance.credit_notes.create')->name('notes.store');
        Route::get('reports/{report?}', [GstComplianceController::class, 'reports'])->middleware('can:compliance.gst.reports.view')->name('reports.index');
        Route::get('exports', [GstComplianceController::class, 'exports'])->middleware('can:compliance.gst.exports.create')->name('exports.index');
        Route::post('exports/download', [GstComplianceController::class, 'downloadExport'])->middleware('can:compliance.gst.exports.create')->name('exports.download');
        Route::get('filing-guide', [GstComplianceController::class, 'guide'])->name('guide');
        Route::get('periods', [GstComplianceController::class, 'periods'])->middleware('can:compliance.gst.periods.review')->name('periods.index');
        Route::put('periods/{period}', [GstComplianceController::class, 'transitionPeriod'])->middleware('can:compliance.gst.periods.lock')->name('periods.transition');
        Route::get('document-series', [GstComplianceController::class, 'series'])->name('series.index');
        Route::get('e-way-bills', [GstComplianceController::class, 'eway'])->middleware('can:compliance.ewaybill.validate')->name('eway.index');
    });

    Route::middleware(['role:administrator,manager', 'can:cms.view'])->prefix('cms')->name('cms.')->group(function (): void {
        Route::get('/', CmsDashboardController::class)->middleware('can:cms.website_builder.view')->name('dashboard');

        Route::get('branding', [CmsLegacyRouteRedirectController::class, 'branding'])->middleware('can:cms.branding.manage')->name('branding.index');
        Route::put('branding', [CmsBrandingController::class, 'update'])->middleware('can:cms.branding.manage')->name('branding.update');
        Route::get('theme', [CmsThemeController::class, 'index'])->middleware('can:cms.theme.manage')->name('theme.index');
        Route::put('theme', [CmsThemeController::class, 'update'])->middleware('can:cms.theme.manage')->name('theme.update');
        Route::get('header', [CmsLegacyRouteRedirectController::class, 'header'])->middleware('can:cms.header.manage')->name('header.index');
        Route::put('header', [CmsHeaderController::class, 'update'])->middleware('can:cms.header.manage')->name('header.update');
        Route::get('footer', [CmsFooterBuilderController::class, 'index'])->middleware('can:cms.footer.manage')->name('footer.index');
        Route::put('footer', [CmsFooterBuilderController::class, 'update'])->middleware('can:cms.footer.manage')->name('footer.update');

        Route::get('content', [CmsContentEditorController::class, 'index'])->middleware('can:cms.content.view')->name('content.index');
        Route::get('content/pages', [CmsContentEditorController::class, 'index'])->middleware('can:cms.content.view')->name('content.pages.index');
        Route::get('content/blocks', [CmsContentEditorController::class, 'index'])->middleware('can:cms.content.view')->name('content.blocks.index');
        Route::post('content/pages', [CmsContentEditorController::class, 'store'])->middleware('can:cms.content.create')->name('content.pages.store');
        Route::get('content/pages/{page}', [CmsContentEditorController::class, 'show'])->middleware('can:cms.content.view')->name('content.pages.show');
        Route::put('content/pages/{page}', [CmsContentEditorController::class, 'update'])->middleware('can:cms.content.update')->name('content.pages.update');
        Route::post('content/pages/{page}/publish', [CmsContentEditorController::class, 'publish'])->middleware('can:cms.content.publish')->name('content.pages.publish');
        Route::post('content/pages/{page}/unpublish', [CmsContentEditorController::class, 'unpublish'])->middleware('can:cms.content.publish')->name('content.pages.unpublish');
        Route::post('content/pages/{page}/archive', [CmsContentEditorController::class, 'archive'])->middleware('can:cms.content.delete')->name('content.pages.archive');
        Route::get('content/pages/{page}/preview', [CmsContentEditorController::class, 'preview'])->middleware('can:cms.content.view')->name('content.pages.preview');
        Route::post('content/pages/{page}/sections', [CmsContentEditorController::class, 'storeSection'])->middleware('can:cms.content.update')->name('content.sections.store');
        Route::put('content/pages/{page}/sections/{section}', [CmsContentEditorController::class, 'updateSection'])->middleware('can:cms.content.update')->name('content.sections.update');
        Route::post('content/pages/{page}/sections/{section}/toggle', [CmsContentEditorController::class, 'toggleSection'])->middleware('can:cms.content.update')->name('content.sections.toggle');
        Route::post('content/pages/{page}/sections/{section}/move', [CmsContentEditorController::class, 'moveSection'])->middleware('can:cms.content.update')->name('content.sections.move');
        Route::delete('content/pages/{page}/sections/{section}', [CmsContentEditorController::class, 'destroySection'])->middleware('can:cms.content.delete')->name('content.sections.destroy');
        Route::get('content/navigation', [CmsContentNavigationController::class, 'index'])->middleware('can:cms.navigation.manage')->name('content.navigation.index');
        Route::post('content/navigation', [CmsContentNavigationController::class, 'store'])->middleware('can:cms.navigation.manage')->name('content.navigation.store');
        Route::put('content/navigation/{item}', [CmsContentNavigationController::class, 'update'])->middleware('can:cms.navigation.manage')->name('content.navigation.update');
        Route::get('content/footer', [CmsContentFooterController::class, 'index'])->middleware('can:cms.footer.manage')->name('content.footer.index');
        Route::post('content/footer', [CmsContentFooterController::class, 'store'])->middleware('can:cms.footer.manage')->name('content.footer.store');
        Route::put('content/footer/{block}', [CmsContentFooterController::class, 'update'])->middleware('can:cms.footer.manage')->name('content.footer.update');

        Route::get('pages', [CmsPageController::class, 'index'])->middleware('can:cms.pages.manage')->name('pages.index');
        Route::get('pages/create', [CmsPageController::class, 'create'])->name('pages.create');
        Route::post('pages', [CmsPageController::class, 'store'])->name('pages.store');
        Route::post('pages/bulk', [CmsPageController::class, 'bulk'])->name('pages.bulk');
        Route::get('pages/{page}/edit', [CmsPageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [CmsPageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [CmsPageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/restore', [CmsPageController::class, 'restore'])->name('pages.restore');
        Route::post('pages/{page}/publish', [CmsPageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [CmsPageController::class, 'unpublish'])->name('pages.unpublish');
        Route::get('pages/{page}/revisions', [CmsPageController::class, 'revisions'])->middleware('can:cms.pages.manage')->name('pages.revisions.index');
        Route::post('pages/{page}/revisions/{revision}/restore', [CmsPageController::class, 'restoreRevision'])->middleware('can:cms.pages.manage')->name('pages.revisions.restore');
        Route::post('pages/{page}/preview', [CmsPageController::class, 'preview'])->middleware('can:cms.pages.manage')->name('pages.preview');
        Route::post('pages/{page}/preview/revoke', [CmsPageController::class, 'revokePreview'])->middleware('can:cms.pages.manage')->name('pages.preview.revoke');
        Route::post('pages/{page}/sections', [CmsPageController::class, 'storeSection'])->name('pages.sections.store');
        Route::put('pages/{page}/sections/{section}', [CmsPageController::class, 'updateSection'])->name('pages.sections.update');
        Route::post('pages/{page}/sections/{section}/move', [CmsPageController::class, 'moveSection'])->name('pages.sections.move');
        Route::delete('pages/{page}/sections/{section}', [CmsPageController::class, 'destroySection'])->name('pages.sections.destroy');

        Route::get('homepage', [CmsHomepageController::class, 'index'])->middleware('can:cms.homepage.manage')->name('homepage.index');
        Route::put('homepage/{section}', [CmsHomepageController::class, 'update'])->middleware('can:cms.homepage.manage')->name('homepage.update');

        Route::get('menus', [CmsMenuController::class, 'index'])->name('menus.index');
        Route::post('menus', [CmsMenuController::class, 'store'])->name('menus.store');
        Route::put('menus/{menu}', [CmsMenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [CmsMenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('menus/{menu}/restore', [CmsMenuController::class, 'restore'])->name('menus.restore');
        Route::post('menus/{menu}/items', [CmsMenuController::class, 'storeItem'])->name('menus.items.store');
        Route::put('menus/{menu}/items/{item}', [CmsMenuController::class, 'updateItem'])->name('menus.items.update');

        Route::get('media', [CmsMediaController::class, 'index'])->middleware('can:cms.media.manage')->name('media.index');
        Route::post('media', [CmsMediaController::class, 'store'])->name('media.store');
        Route::post('media/folders', [CmsMediaController::class, 'storeFolder'])->name('media.folders.store');
        Route::delete('media/{media}', [CmsMediaController::class, 'destroy'])->name('media.destroy');

        Route::get('settings', [CmsAdminSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [CmsAdminSettingsController::class, 'update'])->name('settings.update');
        Route::put('settings/footer', [CmsAdminSettingsController::class, 'updateFooter'])->name('settings.footer.update');

        Route::get('seo', [CmsSeoController::class, 'index'])->middleware('can:cms.seo.manage')->name('seo.index');
        Route::put('seo', [CmsSeoController::class, 'update'])->name('seo.update');
        Route::post('seo/redirects', [CmsSeoController::class, 'storeRedirect'])->name('seo.redirects.store');

        Route::get('seo-pages', [CmsSeoPageController::class, 'index'])->middleware('can:cms.pages.manage')->name('seo-pages.index');
        Route::get('seo-pages/create', [CmsSeoPageController::class, 'create'])->middleware('can:cms.pages.manage')->name('seo-pages.create');
        Route::post('seo-pages', [CmsSeoPageController::class, 'store'])->middleware('can:cms.pages.manage')->name('seo-pages.store');
        Route::get('seo-pages/{page}/edit', [CmsSeoPageController::class, 'edit'])->middleware('can:cms.pages.manage')->name('seo-pages.edit');
        Route::put('seo-pages/{page}', [CmsSeoPageController::class, 'update'])->middleware('can:cms.pages.manage')->name('seo-pages.update');
        Route::post('seo-pages/{page}/publish', [CmsSeoPageController::class, 'publish'])->middleware('can:cms.pages.manage')->name('seo-pages.publish');
        Route::post('seo-pages/{page}/unpublish', [CmsSeoPageController::class, 'unpublish'])->middleware('can:cms.pages.manage')->name('seo-pages.unpublish');
        Route::post('seo-pages/{page}/archive', [CmsSeoPageController::class, 'archive'])->middleware('can:cms.pages.manage')->name('seo-pages.archive');

        Route::get('landing-pages', [CmsLandingPageController::class, 'index'])->middleware('can:cms.pages.manage')->name('landing-pages.index');
        Route::get('landing-pages/create', [CmsLandingPageController::class, 'create'])->middleware('can:cms.pages.manage')->name('landing-pages.create');
        Route::post('landing-pages', [CmsLandingPageController::class, 'store'])->middleware('can:cms.pages.manage')->name('landing-pages.store');
        Route::get('landing-pages/{page}/edit', [CmsLandingPageController::class, 'edit'])->middleware('can:cms.pages.manage')->name('landing-pages.edit');
        Route::put('landing-pages/{page}', [CmsLandingPageController::class, 'update'])->middleware('can:cms.pages.manage')->name('landing-pages.update');
        Route::post('landing-pages/{page}/publish', [CmsLandingPageController::class, 'publish'])->middleware('can:cms.pages.manage')->name('landing-pages.publish');
        Route::post('landing-pages/{page}/unpublish', [CmsLandingPageController::class, 'unpublish'])->middleware('can:cms.pages.manage')->name('landing-pages.unpublish');
        Route::post('landing-pages/{page}/archive', [CmsLandingPageController::class, 'archive'])->middleware('can:cms.pages.manage')->name('landing-pages.archive');

        Route::get('articles', [CmsArticleController::class, 'index'])->middleware('can:cms.pages.manage')->name('articles.index');
        Route::get('articles/create', [CmsArticleController::class, 'create'])->middleware('can:cms.pages.manage')->name('articles.create');
        Route::post('articles', [CmsArticleController::class, 'store'])->middleware('can:cms.pages.manage')->name('articles.store');
        Route::get('articles/{article}/edit', [CmsArticleController::class, 'edit'])->middleware('can:cms.pages.manage')->name('articles.edit');
        Route::put('articles/{article}', [CmsArticleController::class, 'update'])->middleware('can:cms.pages.manage')->name('articles.update');
        Route::post('articles/{article}/publish', [CmsArticleController::class, 'publish'])->middleware('can:cms.pages.manage')->name('articles.publish');
        Route::post('articles/{article}/unpublish', [CmsArticleController::class, 'unpublish'])->middleware('can:cms.pages.manage')->name('articles.unpublish');
        Route::post('articles/{article}/archive', [CmsArticleController::class, 'archive'])->middleware('can:cms.pages.manage')->name('articles.archive');

        Route::get('redirects', [CmsRedirectController::class, 'index'])->middleware('can:cms.redirects.manage')->name('redirects.index');
        Route::post('redirects', [CmsRedirectController::class, 'store'])->middleware('can:cms.redirects.manage')->name('redirects.store');
        Route::put('redirects/{redirect}', [CmsRedirectController::class, 'update'])->middleware('can:cms.redirects.manage')->name('redirects.update');
        Route::delete('redirects/{redirect}', [CmsRedirectController::class, 'destroy'])->middleware('can:cms.redirects.manage')->name('redirects.destroy');

        Route::get('client-logos', [CmsLegacyRouteRedirectController::class, 'clientLogos'])->middleware('can:cms.client_logos.manage')->name('client-logos.index');
        Route::post('client-logos', [CmsClientLogoController::class, 'store'])->middleware('can:cms.client_logos.manage')->name('client-logos.store');
        Route::put('client-logos/{logo}', [CmsClientLogoController::class, 'update'])->middleware('can:cms.client_logos.manage')->name('client-logos.update');
        Route::delete('client-logos/{logo}', [CmsClientLogoController::class, 'destroy'])->middleware('can:cms.client_logos.manage')->name('client-logos.destroy');
        Route::post('client-logos/{logo}/restore', [CmsClientLogoController::class, 'restore'])->middleware('can:cms.client_logos.manage')->name('client-logos.restore');

        Route::get('case-studies', [CmsCaseStudyController::class, 'index'])->middleware('can:cms.case_studies.manage')->name('case-studies.index');
        Route::get('case-studies/create', [CmsCaseStudyController::class, 'create'])->middleware('can:cms.case_studies.manage')->name('case-studies.create');
        Route::post('case-studies', [CmsCaseStudyController::class, 'store'])->middleware('can:cms.case_studies.manage')->name('case-studies.store');
        Route::get('case-studies/{caseStudy}/edit', [CmsCaseStudyController::class, 'edit'])->middleware('can:cms.case_studies.manage')->name('case-studies.edit');
        Route::put('case-studies/{caseStudy}', [CmsCaseStudyController::class, 'update'])->middleware('can:cms.case_studies.manage')->name('case-studies.update');
        Route::post('case-studies/{caseStudy}/publish', [CmsCaseStudyController::class, 'publish'])->middleware('can:cms.case_studies.manage')->name('case-studies.publish');
        Route::post('case-studies/{caseStudy}/unpublish', [CmsCaseStudyController::class, 'unpublish'])->middleware('can:cms.case_studies.manage')->name('case-studies.unpublish');
        Route::post('case-studies/{caseStudy}/preview', [CmsCaseStudyController::class, 'preview'])->middleware('can:cms.case_studies.manage')->name('case-studies.preview');
        Route::post('case-studies/{caseStudy}/preview/revoke', [CmsCaseStudyController::class, 'revokePreview'])->middleware('can:cms.case_studies.manage')->name('case-studies.preview.revoke');
        Route::delete('case-studies/{caseStudy}', [CmsCaseStudyController::class, 'destroy'])->middleware('can:cms.case_studies.manage')->name('case-studies.destroy');
        Route::post('case-studies/{caseStudy}/restore', [CmsCaseStudyController::class, 'restore'])->middleware('can:cms.case_studies.manage')->name('case-studies.restore');

        Route::get('testimonials', [CmsTestimonialController::class, 'index'])->middleware('can:cms.testimonials.manage')->name('testimonials.index');
        Route::post('testimonials', [CmsTestimonialController::class, 'store'])->middleware('can:cms.testimonials.manage')->name('testimonials.store');
        Route::put('testimonials/{testimonial}', [CmsTestimonialController::class, 'update'])->middleware('can:cms.testimonials.manage')->name('testimonials.update');
        Route::delete('testimonials/{testimonial}', [CmsTestimonialController::class, 'destroy'])->middleware('can:cms.testimonials.manage')->name('testimonials.destroy');
        Route::post('testimonials/{testimonial}/restore', [CmsTestimonialController::class, 'restore'])->middleware('can:cms.testimonials.manage')->name('testimonials.restore');

        Route::get('trust-metrics', [CmsTrustMetricController::class, 'index'])->middleware('can:cms.trust_metrics.manage')->name('trust-metrics.index');
        Route::post('trust-metrics', [CmsTrustMetricController::class, 'store'])->middleware('can:cms.trust_metrics.manage')->name('trust-metrics.store');
        Route::put('trust-metrics/{metric}', [CmsTrustMetricController::class, 'update'])->middleware('can:cms.trust_metrics.manage')->name('trust-metrics.update');
        Route::delete('trust-metrics/{metric}', [CmsTrustMetricController::class, 'destroy'])->middleware('can:cms.trust_metrics.manage')->name('trust-metrics.destroy');
        Route::post('trust-metrics/{metric}/restore', [CmsTrustMetricController::class, 'restore'])->middleware('can:cms.trust_metrics.manage')->name('trust-metrics.restore');

        Route::get('faqs', [CmsFaqController::class, 'index'])->middleware('can:cms.faq.manage')->name('faqs.index');
        Route::post('faqs', [CmsFaqController::class, 'store'])->middleware('can:cms.faq.manage')->name('faqs.store');
        Route::put('faqs/{faq}', [CmsFaqController::class, 'update'])->middleware('can:cms.faq.manage')->name('faqs.update');
        Route::delete('faqs/{faq}', [CmsFaqController::class, 'destroy'])->middleware('can:cms.faq.manage')->name('faqs.destroy');
        Route::post('faqs/{faq}/restore', [CmsFaqController::class, 'restore'])->middleware('can:cms.faq.manage')->name('faqs.restore');

        Route::get('ctas', [CmsCtaController::class, 'index'])->middleware('can:cms.cta.manage')->name('ctas.index');
        Route::post('ctas', [CmsCtaController::class, 'store'])->middleware('can:cms.cta.manage')->name('ctas.store');
        Route::put('ctas/{cta}', [CmsCtaController::class, 'update'])->middleware('can:cms.cta.manage')->name('ctas.update');
        Route::delete('ctas/{cta}', [CmsCtaController::class, 'destroy'])->middleware('can:cms.cta.manage')->name('ctas.destroy');
        Route::post('ctas/{cta}/restore', [CmsCtaController::class, 'restore'])->middleware('can:cms.cta.manage')->name('ctas.restore');
    });

    Route::middleware(['role:administrator,manager', 'can:cms.view'])->prefix('website')->name('website.')->group(function (): void {
        Route::get('/', CmsDashboardController::class)->name('dashboard');
        Route::get('pages', [CmsPageController::class, 'index'])->middleware('can:website.pages.view')->name('pages.index');
        Route::get('pages/create', [CmsPageController::class, 'create'])->middleware('can:website.pages.create')->name('pages.create');
        Route::post('pages', [CmsPageController::class, 'store'])->middleware('can:website.pages.create')->name('pages.store');
        Route::post('pages/bulk', [CmsPageController::class, 'bulk'])->middleware('can:website.pages.update')->name('pages.bulk');
        Route::get('pages/{page}/edit', [CmsPageController::class, 'edit'])->middleware('can:website.pages.update')->name('pages.edit');
        Route::put('pages/{page}', [CmsPageController::class, 'update'])->middleware('can:website.pages.update')->name('pages.update');
        Route::delete('pages/{page}', [CmsPageController::class, 'destroy'])->middleware('can:website.pages.delete')->name('pages.destroy');
        Route::post('pages/{page}/restore', [CmsPageController::class, 'restore'])->middleware('can:website.pages.delete')->name('pages.restore');
        Route::post('pages/{page}/publish', [CmsPageController::class, 'publish'])->middleware('can:website.pages.publish')->name('pages.publish');
        Route::post('pages/{page}/unpublish', [CmsPageController::class, 'unpublish'])->middleware('can:website.pages.publish')->name('pages.unpublish');
        Route::get('pages/{page}/revisions', [CmsPageController::class, 'revisions'])->middleware('can:website.revisions.view')->name('pages.revisions.index');
        Route::post('pages/{page}/revisions/{revision}/restore', [CmsPageController::class, 'restoreRevision'])->middleware('can:website.revisions.restore')->name('pages.revisions.restore');
        Route::post('pages/{page}/preview', [CmsPageController::class, 'preview'])->middleware('can:website.preview.create')->name('pages.preview');
        Route::post('pages/{page}/preview/revoke', [CmsPageController::class, 'revokePreview'])->middleware('can:website.preview.revoke')->name('pages.preview.revoke');
        Route::post('pages/{page}/sections', [CmsPageController::class, 'storeSection'])->middleware('can:website.sections.manage')->name('pages.sections.store');
        Route::put('pages/{page}/sections/{section}', [CmsPageController::class, 'updateSection'])->middleware('can:website.sections.manage')->name('pages.sections.update');
        Route::post('pages/{page}/sections/{section}/move', [CmsPageController::class, 'moveSection'])->middleware('can:website.sections.manage')->name('pages.sections.move');
        Route::delete('pages/{page}/sections/{section}', [CmsPageController::class, 'destroySection'])->middleware('can:website.sections.manage')->name('pages.sections.destroy');
        Route::get('case-studies', [CmsCaseStudyController::class, 'index'])->middleware('can:website.case_studies.view')->name('case-studies.index');
        Route::get('case-studies/create', [CmsCaseStudyController::class, 'create'])->middleware('can:website.case_studies.create')->name('case-studies.create');
        Route::post('case-studies', [CmsCaseStudyController::class, 'store'])->middleware('can:website.case_studies.create')->name('case-studies.store');
        Route::get('case-studies/{caseStudy}/edit', [CmsCaseStudyController::class, 'edit'])->middleware('can:website.case_studies.update')->name('case-studies.edit');
        Route::put('case-studies/{caseStudy}', [CmsCaseStudyController::class, 'update'])->middleware('can:website.case_studies.update')->name('case-studies.update');
        Route::post('case-studies/{caseStudy}/publish', [CmsCaseStudyController::class, 'publish'])->middleware('can:website.case_studies.publish')->name('case-studies.publish');
        Route::post('case-studies/{caseStudy}/unpublish', [CmsCaseStudyController::class, 'unpublish'])->middleware('can:website.case_studies.publish')->name('case-studies.unpublish');
        Route::post('case-studies/{caseStudy}/preview', [CmsCaseStudyController::class, 'preview'])->middleware('can:website.preview.create')->name('case-studies.preview');
        Route::post('case-studies/{caseStudy}/preview/revoke', [CmsCaseStudyController::class, 'revokePreview'])->middleware('can:website.preview.revoke')->name('case-studies.preview.revoke');
        Route::delete('case-studies/{caseStudy}', [CmsCaseStudyController::class, 'destroy'])->middleware('can:website.case_studies.delete')->name('case-studies.destroy');
        Route::get('media', [CmsMediaController::class, 'index'])->middleware('can:website.media.view')->name('media.index');
        Route::post('media', [CmsMediaController::class, 'store'])->middleware('can:website.media.upload')->name('media.store');
        Route::delete('media/{media}', [CmsMediaController::class, 'destroy'])->middleware('can:website.media.delete')->name('media.destroy');
        Route::get('navigation', [CmsMenuController::class, 'index'])->middleware('can:website.navigation.view')->name('navigation.index');
        Route::post('navigation', [CmsMenuController::class, 'store'])->middleware('can:website.navigation.update')->name('navigation.store');
        Route::put('navigation/{menu}', [CmsMenuController::class, 'update'])->middleware('can:website.navigation.update')->name('navigation.update');
        Route::post('navigation/{menu}/items', [CmsMenuController::class, 'storeItem'])->middleware('can:website.navigation.update')->name('navigation.items.store');
        Route::put('navigation/{menu}/items/{item}', [CmsMenuController::class, 'updateItem'])->middleware('can:website.navigation.update')->name('navigation.items.update');
        Route::get('settings', [CmsAdminSettingsController::class, 'index'])->middleware('can:website.settings.view')->name('settings.index');
        Route::put('settings', [CmsAdminSettingsController::class, 'update'])->middleware('can:website.settings.update')->name('settings.update');
        Route::get('import', [CmsImportController::class, 'index'])->middleware(['role:administrator', 'can:website.import.view'])->name('import.index');
        Route::post('import', [CmsImportController::class, 'store'])->middleware(['role:administrator', 'can:website.import.execute'])->name('import.store');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:notifications.view'])->prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationInboxController::class, 'index'])->middleware('can:notifications.manage_own')->name('index');
        Route::post('read-all', [NotificationInboxController::class, 'markAllRead'])->middleware('can:notifications.manage_own')->name('read-all');
        Route::post('{notification}/read', [NotificationInboxController::class, 'markRead'])->middleware('can:notifications.manage_own')->name('read');
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

    Route::middleware(['role:administrator,manager', 'can:operations.view'])->prefix('operations')->name('operations.')->group(function (): void {
        Route::get('/', OperationsDashboardController::class)->name('dashboard');

        Route::get('health', [HealthCheckController::class, 'index'])->middleware('can:operations.health.view')->name('health.index');
        Route::post('health/run', [HealthCheckController::class, 'run'])->middleware('can:operations.settings.manage')->name('health.run');

        Route::get('queue', [QueueMonitorController::class, 'index'])->middleware('can:operations.queue.view')->name('queue.index');
        Route::post('queue/snapshot', [QueueMonitorController::class, 'capture'])->middleware('can:operations.settings.manage')->name('queue.snapshot');

        Route::get('failed-jobs', [FailedJobController::class, 'index'])->middleware('can:operations.failed_jobs.view')->name('failed-jobs.index');
        Route::post('failed-jobs/{failedJob}/retry', [FailedJobController::class, 'retry'])->middleware('can:operations.failed_jobs.retry')->name('failed-jobs.retry');
        Route::post('failed-jobs/bulk-retry', [FailedJobController::class, 'bulkRetry'])->middleware('can:operations.failed_jobs.retry')->name('failed-jobs.bulk-retry');
        Route::delete('failed-jobs/bulk-delete', [FailedJobController::class, 'bulkDestroy'])->middleware('can:operations.failed_jobs.delete')->name('failed-jobs.bulk-destroy');
        Route::delete('failed-jobs/{failedJob}', [FailedJobController::class, 'destroy'])->middleware('can:operations.failed_jobs.delete')->name('failed-jobs.destroy');

        Route::get('schedule', ScheduleMonitorController::class)->middleware('can:operations.schedule.view')->name('schedule.index');
        Route::get('application', ApplicationInfoController::class)->middleware('can:operations.application.view')->name('application.index');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:inventory.view'])->prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/', InventoryDashboardController::class)->name('dashboard');
        Route::get('decision-dashboard', InventoryDecisionDashboardController::class)->middleware('can:inventory.decision_dashboard.view')->name('decision-dashboard');

        Route::get('products', [ProductController::class, 'index'])->middleware('can:inventory.products.view')->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->middleware('can:inventory.products.create')->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->middleware('can:inventory.products.create')->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('can:inventory.products.view')->name('products.show');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('can:inventory.products.update')->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('can:inventory.products.update')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('can:inventory.products.delete')->name('products.destroy');
        Route::post('products/{product}/restore', [ProductController::class, 'restore'])->middleware('can:inventory.products.restore')->name('products.restore');

        Route::resource('categories', InventoryCategoryController::class)->except(['show'])->middleware('can:inventory.categories.manage');
        Route::post('categories/{category}/restore', [InventoryCategoryController::class, 'restore'])->middleware('can:inventory.categories.manage')->name('categories.restore');
        Route::resource('brands', InventoryBrandController::class)->except(['show'])->middleware('can:inventory.brands.manage');
        Route::post('brands/{brand}/restore', [InventoryBrandController::class, 'restore'])->middleware('can:inventory.brands.manage')->name('brands.restore');
        Route::resource('units', InventoryUnitController::class)->except(['show'])->middleware('can:inventory.units.manage');
        Route::post('units/{unit}/restore', [InventoryUnitController::class, 'restore'])->middleware('can:inventory.units.manage')->name('units.restore');
        Route::resource('tax-rates', InventoryTaxRateController::class)->parameters(['tax-rates' => 'tax_rate'])->except(['show'])->middleware('can:inventory.tax.manage');
        Route::post('tax-rates/{tax_rate}/restore', [InventoryTaxRateController::class, 'restore'])->middleware('can:inventory.tax.manage')->name('tax-rates.restore');

        Route::resource('warehouses', WarehouseController::class)->except(['show'])->middleware('can:inventory.warehouses.manage');
        Route::post('warehouses/{warehouse}/restore', [WarehouseController::class, 'restore'])->middleware('can:inventory.warehouses.manage')->name('warehouses.restore');
        Route::resource('locations', StockLocationController::class)->except(['show'])->middleware('can:inventory.warehouses.manage');
        Route::post('locations/{location}/restore', [StockLocationController::class, 'restore'])->middleware('can:inventory.warehouses.manage')->name('locations.restore');

        Route::get('stock-ledger', StockLedgerController::class)->middleware('can:inventory.stock.view')->name('stock.ledger');
        Route::get('opening-stock', [OpeningStockController::class, 'create'])->middleware('can:inventory.stock.opening')->name('opening-stock.create');
        Route::post('opening-stock', [OpeningStockController::class, 'store'])->middleware('can:inventory.stock.opening')->name('opening-stock.store');
        Route::get('adjustments', [StockAdjustmentController::class, 'index'])->middleware('can:inventory.stock.adjust')->name('adjustments.index');
        Route::get('adjustments/create', [StockAdjustmentController::class, 'create'])->middleware('can:inventory.stock.adjust')->name('adjustments.create');
        Route::post('adjustments', [StockAdjustmentController::class, 'store'])->middleware('can:inventory.stock.adjust')->name('adjustments.store');
        Route::get('adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->middleware('can:inventory.stock.adjust')->name('adjustments.show');
        Route::post('adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->middleware('can:inventory.stock.approve_adjustment')->name('adjustments.approve');

        Route::get('barcode-templates', [BarcodeLabelTemplateController::class, 'index'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.index');
        Route::get('barcode-templates/create', [BarcodeLabelTemplateController::class, 'create'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.create');
        Route::post('barcode-templates', [BarcodeLabelTemplateController::class, 'store'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.store');
        Route::get('barcode-templates/{template}/edit', [BarcodeLabelTemplateController::class, 'edit'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.edit');
        Route::put('barcode-templates/{template}', [BarcodeLabelTemplateController::class, 'update'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.update');
        Route::post('barcode-templates/{template}/default', [BarcodeLabelTemplateController::class, 'setDefault'])->middleware('can:inventory.barcode.manage')->name('barcode-templates.default');
        Route::get('barcode-batches', [BarcodePrintBatchController::class, 'index'])->middleware('can:inventory.barcode.print')->name('barcode-batches.index');
        Route::get('barcode-batches/create', [BarcodePrintBatchController::class, 'create'])->middleware('can:inventory.barcode.print')->name('barcode-batches.create');
        Route::post('barcode-batches', [BarcodePrintBatchController::class, 'store'])->middleware('can:inventory.barcode.print')->name('barcode-batches.store');
        Route::get('barcode-batches/{batch}', [BarcodePrintBatchController::class, 'show'])->middleware('can:inventory.barcode.print')->name('barcode-batches.show');

        Route::get('reorder', [ReorderSuggestionController::class, 'index'])->middleware('can:inventory.reorder.view')->name('reorder.index');
        Route::post('reorder/rules', [ReorderSuggestionController::class, 'storeRule'])->middleware('can:inventory.reorder.manage')->name('reorder.rules.store');
        Route::post('reorder/rules/{rule}/generate', [ReorderSuggestionController::class, 'generate'])->middleware('can:inventory.reorder.manage')->name('reorder.rules.generate');
        Route::post('reorder/suggestions/{suggestion}/review', [ReorderSuggestionController::class, 'review'])->middleware('can:inventory.reorder.manage')->name('reorder.suggestions.review');
        Route::post('reorder/suggestions/{suggestion}/dismiss', [ReorderSuggestionController::class, 'dismiss'])->middleware('can:inventory.reorder.manage')->name('reorder.suggestions.dismiss');

        Route::resource('channels', SalesChannelController::class)->except(['show', 'destroy'])->middleware('can:inventory.channels.manage');
        Route::post('channels/{channel}/warning', [SalesChannelController::class, 'warning'])->middleware('can:inventory.channels.manage')->name('channels.warning');
        Route::get('channel-mappings', [ChannelProductMappingController::class, 'index'])->middleware('can:inventory.channels.view')->name('channel-mappings.index');
        Route::post('channel-mappings', [ChannelProductMappingController::class, 'store'])->middleware('can:inventory.channels.manage')->name('channel-mappings.store');

        Route::get('settings', [InventorySettingsController::class, 'index'])->middleware('can:inventory.settings.manage')->name('settings.index');
        Route::put('settings', [InventorySettingsController::class, 'update'])->middleware('can:inventory.settings.manage')->name('settings.update');
    });

    Route::middleware(['role:administrator,manager', 'can:purchases.view'])->prefix('purchases')->name('purchases.')->group(function (): void {
        Route::get('/', PurchaseDashboardController::class)->middleware('can:purchases.dashboard.view')->name('dashboard');
        Route::get('supplier-dashboard', SupplierDashboardController::class)->middleware('can:purchases.supplier_dashboard.view')->name('supplier-dashboard');

        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('can:purchases.suppliers.view')->name('suppliers.index');
        Route::get('suppliers/create', [SupplierController::class, 'create'])->middleware('can:purchases.suppliers.create')->name('suppliers.create');
        Route::post('suppliers', [SupplierController::class, 'store'])->middleware('can:purchases.suppliers.create')->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('can:purchases.suppliers.view')->name('suppliers.show');
        Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('can:purchases.suppliers.update')->name('suppliers.edit');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('can:purchases.suppliers.update')->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('can:purchases.suppliers.delete')->name('suppliers.destroy');
        Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->middleware('can:purchases.suppliers.restore')->name('suppliers.restore');
        Route::post('suppliers/{supplier}/contacts', [SupplierController::class, 'storeContact'])->middleware('can:purchases.suppliers.update')->name('suppliers.contacts.store');
        Route::post('suppliers/{supplier}/addresses', [SupplierController::class, 'storeAddress'])->middleware('can:purchases.suppliers.update')->name('suppliers.addresses.store');
        Route::post('suppliers/{supplier}/products', [SupplierController::class, 'storeProduct'])->middleware('can:purchases.supplier_products.manage')->name('suppliers.products.store');
        Route::post('suppliers/{supplier}/score', [SupplierController::class, 'score'])->middleware('can:purchases.supplier_scores.manage')->name('suppliers.score');

        Route::get('requests', [PurchaseRequestController::class, 'index'])->middleware('can:purchases.requests.view')->name('requests.index');
        Route::get('requests/create', [PurchaseRequestController::class, 'create'])->middleware('can:purchases.requests.create')->name('requests.create');
        Route::post('requests', [PurchaseRequestController::class, 'store'])->middleware('can:purchases.requests.create')->name('requests.store');
        Route::post('requests/from-reorder', [PurchaseRequestController::class, 'createFromReorder'])->middleware('can:purchases.requests.create')->name('requests.from-reorder');
        Route::get('requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->middleware('can:purchases.requests.view')->name('requests.show');
        Route::post('requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->middleware('can:purchases.requests.update')->name('requests.submit');
        Route::post('requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->middleware('can:purchases.requests.approve')->name('requests.approve');
        Route::post('requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->middleware('can:purchases.requests.reject')->name('requests.reject');
        Route::post('requests/{purchaseRequest}/convert', [PurchaseRequestController::class, 'convert'])->middleware('can:purchases.requests.convert')->name('requests.convert');

        Route::get('orders', [PurchaseOrderController::class, 'index'])->middleware('can:purchases.orders.view')->name('orders.index');
        Route::get('orders/create', [PurchaseOrderController::class, 'create'])->middleware('can:purchases.orders.create')->name('orders.create');
        Route::post('orders', [PurchaseOrderController::class, 'store'])->middleware('can:purchases.orders.create')->name('orders.store');
        Route::get('orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('can:purchases.orders.view')->name('orders.show');
        Route::get('orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->middleware('can:purchases.orders.view')->name('orders.print');
        Route::post('orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('can:purchases.orders.update')->name('orders.submit');
        Route::post('orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('can:purchases.orders.approve')->name('orders.approve');
        Route::post('orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('can:purchases.orders.send')->name('orders.send');
        Route::post('orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('can:purchases.orders.cancel')->name('orders.cancel');

        Route::get('grn', [GoodsReceiptController::class, 'index'])->middleware('can:purchases.grn.view')->name('grn.index');
        Route::get('grn/create', [GoodsReceiptController::class, 'create'])->middleware('can:purchases.grn.create')->name('grn.create');
        Route::post('grn', [GoodsReceiptController::class, 'store'])->middleware('can:purchases.grn.create')->name('grn.store');
        Route::get('grn/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('can:purchases.grn.view')->name('grn.show');
        Route::post('grn/{goodsReceipt}/receive', [GoodsReceiptController::class, 'receive'])->middleware('can:purchases.grn.receive')->name('grn.receive');

        Route::get('returns', [PurchaseReturnController::class, 'index'])->middleware('can:purchases.returns.view')->name('returns.index');
        Route::get('returns/create', [PurchaseReturnController::class, 'create'])->middleware('can:purchases.returns.create')->name('returns.create');
        Route::post('returns', [PurchaseReturnController::class, 'store'])->middleware('can:purchases.returns.create')->name('returns.store');
        Route::get('returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->middleware('can:purchases.returns.view')->name('returns.show');
        Route::post('returns/{purchaseReturn}/approve', [PurchaseReturnController::class, 'approve'])->middleware('can:purchases.returns.approve')->name('returns.approve');
        Route::post('returns/{purchaseReturn}/complete', [PurchaseReturnController::class, 'complete'])->middleware('can:purchases.returns.complete')->name('returns.complete');

        Route::get('settings', [PurchaseSettingsController::class, 'index'])->middleware('can:purchases.settings.manage')->name('settings.index');
        Route::put('settings', [PurchaseSettingsController::class, 'update'])->middleware('can:purchases.settings.manage')->name('settings.update');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:promotions.view'])->prefix('promotions')->name('promotions.')->group(function (): void {
        Route::get('/', PromotionDashboardController::class)->middleware('can:promotions.dashboard.view')->name('dashboard');

        Route::get('campaigns', [PromotionCampaignController::class, 'index'])->middleware('can:promotions.campaigns.view')->name('campaigns.index');
        Route::get('campaigns/create', [PromotionCampaignController::class, 'create'])->middleware('can:promotions.campaigns.create')->name('campaigns.create');
        Route::post('campaigns', [PromotionCampaignController::class, 'store'])->middleware('can:promotions.campaigns.create')->name('campaigns.store');
        Route::get('campaigns/{campaign}', [PromotionCampaignController::class, 'show'])->middleware('can:promotions.campaigns.view')->name('campaigns.show');
        Route::get('campaigns/{campaign}/edit', [PromotionCampaignController::class, 'edit'])->middleware('can:promotions.campaigns.update')->name('campaigns.edit');
        Route::put('campaigns/{campaign}', [PromotionCampaignController::class, 'update'])->middleware('can:promotions.campaigns.update')->name('campaigns.update');
        Route::delete('campaigns/{campaign}', [PromotionCampaignController::class, 'destroy'])->middleware('can:promotions.campaigns.delete')->name('campaigns.destroy');
        Route::post('campaigns/{campaign}/restore', [PromotionCampaignController::class, 'restore'])->middleware('can:promotions.campaigns.restore')->name('campaigns.restore');

        Route::get('rules', [PromotionRuleController::class, 'index'])->middleware('can:promotions.rules.view')->name('rules.index');
        Route::get('rules/create', [PromotionRuleController::class, 'create'])->middleware('can:promotions.rules.create')->name('rules.create');
        Route::post('rules', [PromotionRuleController::class, 'store'])->middleware('can:promotions.rules.create')->name('rules.store');
        Route::get('rules/{rule}', [PromotionRuleController::class, 'show'])->middleware('can:promotions.rules.view')->name('rules.show');
        Route::get('rules/{rule}/edit', [PromotionRuleController::class, 'edit'])->middleware('can:promotions.rules.update')->name('rules.edit');
        Route::put('rules/{rule}', [PromotionRuleController::class, 'update'])->middleware('can:promotions.rules.update')->name('rules.update');
        Route::delete('rules/{rule}', [PromotionRuleController::class, 'destroy'])->middleware('can:promotions.rules.delete')->name('rules.destroy');
        Route::post('rules/{rule}/restore', [PromotionRuleController::class, 'restore'])->middleware('can:promotions.rules.restore')->name('rules.restore');
        Route::post('rules/{rule}/activate', [PromotionRuleController::class, 'activate'])->middleware('can:promotions.rules.activate')->name('rules.activate');
        Route::post('rules/{rule}/pause', [PromotionRuleController::class, 'pause'])->middleware('can:promotions.rules.pause')->name('rules.pause');
        Route::post('rules/{rule}/approve', [PromotionRuleController::class, 'approve'])->middleware('can:promotions.rules.approve')->name('rules.approve');

        Route::get('coupons', [PromotionCouponController::class, 'index'])->middleware('can:promotions.coupons.view')->name('coupons.index');
        Route::get('coupons/create', [PromotionCouponController::class, 'create'])->middleware('can:promotions.coupons.create')->name('coupons.create');
        Route::post('coupons', [PromotionCouponController::class, 'store'])->middleware('can:promotions.coupons.create')->name('coupons.store');
        Route::get('coupons/{coupon}/edit', [PromotionCouponController::class, 'edit'])->middleware('can:promotions.coupons.update')->name('coupons.edit');
        Route::put('coupons/{coupon}', [PromotionCouponController::class, 'update'])->middleware('can:promotions.coupons.update')->name('coupons.update');
        Route::post('coupons/{coupon}/toggle', [PromotionCouponController::class, 'toggle'])->middleware('can:promotions.coupons.disable')->name('coupons.toggle');

        Route::get('simulator', [PromotionSimulatorController::class, 'index'])->middleware('can:promotions.simulator.view')->name('simulator.index');
        Route::post('simulator', [PromotionSimulatorController::class, 'run'])->middleware('can:promotions.simulator.run')->name('simulator.run');
        Route::get('usage', PromotionUsageController::class)->middleware('can:promotions.usage.view')->name('usage.index');
        Route::get('settings', [PromotionSettingsController::class, 'index'])->middleware('can:promotions.settings.manage')->name('settings.index');
        Route::put('settings', [PromotionSettingsController::class, 'update'])->middleware('can:promotions.settings.manage')->name('settings.update');
    });

    Route::redirect('settings', 'settings/general')->name('settings.index');
    Route::middleware('role:administrator,manager')->group(function (): void {
        Route::get('settings/{section}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
    });
});

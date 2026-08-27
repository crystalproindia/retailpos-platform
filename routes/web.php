<?php

use App\Http\Controllers\Auth\AccountVerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CommandCenter\AiForecastController;
use App\Http\Controllers\CommandCenter\Attendance\AttendanceCalendarController;
use App\Http\Controllers\CommandCenter\Attendance\AttendanceController;
use App\Http\Controllers\CommandCenter\Attendance\LeaveController;
use App\Http\Controllers\CommandCenter\Attendance\RosterController;
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
use App\Http\Controllers\CommandCenter\CompanyProfileController;
use App\Http\Controllers\CommandCenter\Compliance\GstComplianceController;
use App\Http\Controllers\CommandCenter\Compliance\GstNoteController;
use App\Http\Controllers\CommandCenter\Compliance\GstSettingsController;
use App\Http\Controllers\CommandCenter\Crm\ActivityController;
use App\Http\Controllers\CommandCenter\Crm\AiLeadAssistantController;
use App\Http\Controllers\CommandCenter\Crm\ContactController;
use App\Http\Controllers\CommandCenter\Crm\CrmCompanyController;
use App\Http\Controllers\CommandCenter\Crm\CrmCustomerController;
use App\Http\Controllers\CommandCenter\Crm\CrmDashboardController;
use App\Http\Controllers\CommandCenter\Crm\CrmInvoiceReturnController;
use App\Http\Controllers\CommandCenter\Crm\CrmOnboardingController;
use App\Http\Controllers\CommandCenter\Crm\CrmReportController;
use App\Http\Controllers\CommandCenter\Crm\CrmSupportTicketController;
use App\Http\Controllers\CommandCenter\Crm\CustomerPortalAccessController as CrmCustomerPortalAccessController;
use App\Http\Controllers\CommandCenter\Crm\DemoScheduleController;
use App\Http\Controllers\CommandCenter\Crm\FollowUpController;
use App\Http\Controllers\CommandCenter\Crm\InvoiceAmendmentController;
use App\Http\Controllers\CommandCenter\Crm\InvoiceController;
use App\Http\Controllers\CommandCenter\Crm\InvoiceReminderSettingsController;
use App\Http\Controllers\CommandCenter\Crm\InvoiceTemplateController;
use App\Http\Controllers\CommandCenter\Crm\LeadController;
use App\Http\Controllers\CommandCenter\Crm\LeadMasterDataController;
use App\Http\Controllers\CommandCenter\Crm\OpportunityController;
use App\Http\Controllers\CommandCenter\Crm\PipelineController;
use App\Http\Controllers\CommandCenter\Crm\ProformaController;
use App\Http\Controllers\CommandCenter\Crm\ProformaShareController;
use App\Http\Controllers\CommandCenter\Crm\QuotationController;
use App\Http\Controllers\CommandCenter\Crm\QuotationShareController;
use App\Http\Controllers\CommandCenter\Crm\SalesDocumentSettingsController;
use App\Http\Controllers\CommandCenter\Customers\CustomerController;
use App\Http\Controllers\CommandCenter\Customers\CustomerDashboardController;
use App\Http\Controllers\CommandCenter\Customers\CustomerGroupController;
use App\Http\Controllers\CommandCenter\Customers\CustomerIntelligenceController;
use App\Http\Controllers\CommandCenter\Customers\CustomerLoyaltyController;
use App\Http\Controllers\CommandCenter\Customers\CustomerSettingsController;
use App\Http\Controllers\CommandCenter\Customers\CustomerWalletController;
use App\Http\Controllers\CommandCenter\DashboardController;
use App\Http\Controllers\CommandCenter\Finance\FinanceController;
use App\Http\Controllers\CommandCenter\Free365OnboardingController;
use App\Http\Controllers\CommandCenter\Integrations\EmailDeliveryLogController;
use App\Http\Controllers\CommandCenter\Integrations\EmailIntegrationController;
use App\Http\Controllers\CommandCenter\Inventory\BarcodeLabelTemplateController;
use App\Http\Controllers\CommandCenter\Inventory\BarcodePrintBatchController;
use App\Http\Controllers\CommandCenter\Inventory\ChannelProductMappingController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryBrandController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryCategoryController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryDashboardController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryIntelligenceController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryReportController;
use App\Http\Controllers\CommandCenter\Inventory\InventorySettingsController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryTaxRateController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryTraceabilityController;
use App\Http\Controllers\CommandCenter\Inventory\InventoryUnitController;
use App\Http\Controllers\CommandCenter\Inventory\OpeningStockController;
use App\Http\Controllers\CommandCenter\Inventory\ProductController;
use App\Http\Controllers\CommandCenter\Inventory\ReorderSuggestionController;
use App\Http\Controllers\CommandCenter\Inventory\SalesChannelController;
use App\Http\Controllers\CommandCenter\Inventory\StockAdjustmentController;
use App\Http\Controllers\CommandCenter\Inventory\StockAvailabilityController;
use App\Http\Controllers\CommandCenter\Inventory\StockCountController;
use App\Http\Controllers\CommandCenter\Inventory\StockLedgerController;
use App\Http\Controllers\CommandCenter\Inventory\StockLocationController;
use App\Http\Controllers\CommandCenter\Inventory\StockTransferController;
use App\Http\Controllers\CommandCenter\Inventory\WarehouseController;
use App\Http\Controllers\CommandCenter\ModuleController;
use App\Http\Controllers\CommandCenter\NavigationPreferenceController;
use App\Http\Controllers\CommandCenter\Notifications\DeliveryLogController;
use App\Http\Controllers\CommandCenter\Notifications\EventLogController;
use App\Http\Controllers\CommandCenter\Notifications\NotificationAutomationController;
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
use App\Http\Controllers\CommandCenter\OutletController;
use App\Http\Controllers\CommandCenter\Pos\PosController;
use App\Http\Controllers\CommandCenter\Pos\PosOfflineController;
use App\Http\Controllers\CommandCenter\Pos\PosRegisterController;
use App\Http\Controllers\CommandCenter\Pos\PosReturnController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionCampaignController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionCouponController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionDashboardController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionRuleController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionSettingsController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionSimulatorController;
use App\Http\Controllers\CommandCenter\Promotions\PromotionUsageController;
use App\Http\Controllers\CommandCenter\Purchases\GoodsReceiptController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseDashboardController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseInvoiceController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseOrderController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseReportController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseRequestController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseReturnController;
use App\Http\Controllers\CommandCenter\Purchases\PurchaseSettingsController;
use App\Http\Controllers\CommandCenter\Purchases\SupplierController;
use App\Http\Controllers\CommandCenter\Purchases\SupplierDashboardController;
use App\Http\Controllers\CommandCenter\Purchases\SupplierPaymentController;
use App\Http\Controllers\CommandCenter\ReportsController;
use App\Http\Controllers\CommandCenter\Saas\SaasBillingController;
use App\Http\Controllers\CommandCenter\Saas\SaasBillingGatewayController;
use App\Http\Controllers\CommandCenter\Saas\SaasDashboardController;
use App\Http\Controllers\CommandCenter\Saas\SaasPlanController;
use App\Http\Controllers\CommandCenter\Saas\SaasResellerController;
use App\Http\Controllers\CommandCenter\Saas\SaasSubscriptionController;
use App\Http\Controllers\CommandCenter\Saas\SaasTenantOnboardingController;
use App\Http\Controllers\CommandCenter\Saas\TenantBillingController;
use App\Http\Controllers\CommandCenter\Saas\TenantSubscriptionController;
use App\Http\Controllers\CommandCenter\Saas\WhiteLabelController;
use App\Http\Controllers\CommandCenter\SettingsController;
use App\Http\Controllers\CommandCenter\StoreSetupWizardController;
use App\Http\Controllers\CommandCenter\Tasks\TaskController;
use App\Http\Controllers\CommandCenter\Tasks\TaskRuleSettingController;
use App\Http\Controllers\CommandCenter\WorkforceController;
use App\Http\Controllers\Portal\CustomerPortalAccessController;
use App\Http\Controllers\Portal\CustomerPortalController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\PublicProformaController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PublicSaasSignupController;
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

Route::prefix('start-free')->name('saas.public-signup.')->group(function (): void {
    Route::get('/', [PublicSaasSignupController::class, 'show'])->name('show');
    Route::post('verification', [PublicSaasSignupController::class, 'begin'])->middleware('throttle:public-signup')->name('begin');
    Route::post('verification/confirm', [PublicSaasSignupController::class, 'verify'])->middleware('throttle:public-signup-otp')->name('verify');
    Route::post('verification/resend', [PublicSaasSignupController::class, 'resend'])->middleware('throttle:public-signup-otp')->name('resend');
    Route::post('store', [PublicSaasSignupController::class, 'complete'])->middleware('throttle:public-signup')->name('complete');
    Route::get('success', [PublicSaasSignupController::class, 'success'])->name('success');
});

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

Route::get('workforce/invitations/{token}', [WorkforceController::class, 'showInvitation'])
    ->middleware('throttle:workforce-invitation')
    ->name('workforce.invitation.show');
Route::post('workforce/invitations/{token}', [WorkforceController::class, 'acceptInvitation'])
    ->middleware('throttle:workforce-invitation')
    ->name('workforce.invitation.accept');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'workforce.account.active'])->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('account/verification', [AccountVerificationController::class, 'show'])->name('account.verification.show');
    Route::post('account/verification', [AccountVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('account.verification.verify');
    Route::post('account/verification/resend', [AccountVerificationController::class, 'resend'])->middleware('throttle:3,1')->name('account.verification.resend');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('navigation/customize', [NavigationPreferenceController::class, 'edit'])->name('navigation.preferences.edit');
    Route::put('navigation/customize', [NavigationPreferenceController::class, 'update'])->name('navigation.preferences.update');
    Route::post('navigation/customize/reset', [NavigationPreferenceController::class, 'reset'])->name('navigation.preferences.reset');
    Route::middleware('role:administrator,manager')->prefix('finance')->name('finance.')->group(function (): void {
        Route::get('receivables', [FinanceController::class, 'receivables'])->middleware('can:finance.receivables.view')->name('receivables.index');
        Route::get('receivables.csv', [FinanceController::class, 'receivablesCsv'])->middleware('can:finance.exports')->name('receivables.csv');
        Route::get('payables', [FinanceController::class, 'payables'])->middleware('can:finance.payables.view')->name('payables.index');
        Route::get('payables.csv', [FinanceController::class, 'payablesCsv'])->middleware('can:finance.exports')->name('payables.csv');
        Route::get('customers/{customer}/statement', [FinanceController::class, 'customerStatement'])->middleware('can:finance.statements.view')->name('customer-statements.show');
        Route::get('customers/{customer}/statement.pdf', [FinanceController::class, 'customerPdf'])->middleware('can:finance.statements.view')->name('customer-statements.pdf');
        Route::get('customers/{customer}/statement.csv', [FinanceController::class, 'customerCsv'])->middleware('can:finance.exports')->name('customer-statements.csv');
        Route::get('suppliers/{supplier}/statement', [FinanceController::class, 'supplierStatement'])->middleware('can:finance.statements.view')->name('supplier-statements.show');
        Route::get('suppliers/{supplier}/statement.pdf', [FinanceController::class, 'supplierPdf'])->middleware('can:finance.statements.view')->name('supplier-statements.pdf');
        Route::get('suppliers/{supplier}/statement.csv', [FinanceController::class, 'supplierCsv'])->middleware('can:finance.exports')->name('supplier-statements.csv');
        Route::get('customer-payments/create', [FinanceController::class, 'paymentCreate'])->middleware('can:finance.payments.allocate')->name('customer-payments.create');
        Route::post('customer-payments', [FinanceController::class, 'paymentStore'])->middleware(['can:finance.payments.allocate', 'throttle:30,1'])->name('customer-payments.store');
        Route::post('customer-payments/{payment}/allocations', [FinanceController::class, 'allocatePayment'])->middleware(['can:finance.payments.allocate', 'throttle:30,1'])->name('customer-payments.allocations.store');
        Route::post('customer-credits/apply', [FinanceController::class, 'applyCredit'])->middleware(['can:finance.payments.allocate', 'throttle:30,1'])->name('customer-credits.apply');
        Route::put('customers/{customer}/credit-limit', [FinanceController::class, 'updateCreditLimit'])->middleware('can:finance.credit-limits.manage')->name('customer-credit-limits.update');
        Route::get('reconciliation', [FinanceController::class, 'reconciliation'])->middleware('can:finance.reconciliation.manage')->name('reconciliation.index');
        Route::post('reconciliation', [FinanceController::class, 'reconcile'])->middleware('can:finance.reconciliation.manage')->name('reconciliation.store');
    });
    Route::prefix('attendance')->name('attendance.')->middleware('can:attendance.view_own')->group(function (): void {
        Route::get('me', [AttendanceController::class, 'self'])->name('self');
        Route::post('check-in', [AttendanceController::class, 'checkIn'])->middleware('can:attendance.check_in')->name('check-in');
        Route::post('records/{attendance}/check-out', [AttendanceController::class, 'checkOut'])->middleware('can:attendance.check_out')->name('check-out');
        Route::post('records/{attendance}/breaks', [AttendanceController::class, 'startBreak'])->middleware('can:attendance.check_in')->name('breaks.start');
        Route::post('breaks/{break}/end', [AttendanceController::class, 'endBreak'])->middleware('can:attendance.check_out')->name('breaks.end');
        Route::get('history', [AttendanceController::class, 'history'])->name('history');
        Route::post('records/{attendance}/corrections', [AttendanceController::class, 'requestCorrection'])->middleware('can:attendance.correct_own')->name('corrections.store');
        Route::get('leave', [LeaveController::class, 'self'])->middleware('can:leave.view_own')->name('leave.self');
        Route::post('leave', [LeaveController::class, 'store'])->middleware('can:leave.request')->name('leave.store');
        Route::post('leave/{leave}/withdraw', [LeaveController::class, 'withdraw'])->middleware('can:leave.request')->name('leave.withdraw');
    });
    Route::prefix('tasks')->name('tasks.')->middleware('can:tasks.view')->group(function (): void {
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::get('today', [TaskController::class, 'index'])->defaults('view', 'today')->name('today');
        Route::get('upcoming', [TaskController::class, 'index'])->defaults('view', 'upcoming')->name('upcoming');
        Route::get('overdue', [TaskController::class, 'index'])->defaults('view', 'overdue')->name('overdue');
        Route::get('completed', [TaskController::class, 'index'])->defaults('view', 'completed')->name('completed');
        Route::get('personal', [TaskController::class, 'index'])->defaults('view', 'personal')->name('personal');
        Route::get('work', [TaskController::class, 'index'])->defaults('view', 'work')->name('work');
        Route::get('team', [TaskController::class, 'index'])->defaults('view', 'team')->middleware('can:tasks.view_team')->name('team');
        Route::get('calendar', [TaskController::class, 'calendar'])->name('calendar');
        Route::get('export', [TaskController::class, 'export'])->middleware('can:tasks.export')->name('export');
        Route::post('/', [TaskController::class, 'store'])->middleware('can:tasks.create')->name('store');
        Route::get('{task}', [TaskController::class, 'show'])->whereNumber('task')->name('show');
        Route::put('{task}', [TaskController::class, 'update'])->whereNumber('task')->middleware('can:tasks.update_own')->name('update');
        Route::post('{task}/transition', [TaskController::class, 'transition'])->whereNumber('task')->middleware('can:tasks.update_own')->name('transition');
        Route::post('{task}/archive', [TaskController::class, 'archive'])->whereNumber('task')->middleware('can:tasks.archive')->name('archive');
        Route::get('settings/rules', [TaskRuleSettingController::class, 'index'])->middleware('can:tasks.rules.manage')->name('rules.index');
        Route::put('settings/rules/{rule}', [TaskRuleSettingController::class, 'update'])->middleware('can:tasks.rules.manage')->name('rules.update');
    });
    Route::prefix('reports')->name('reports.')->middleware('can:crm.reports.view')->group(function (): void {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('executive/export', [ReportsController::class, 'exportExecutive'])->middleware('can:crm.reports.export')->name('executive.export');
        Route::get('{report}', [ReportsController::class, 'show'])->whereIn('report', ['sales', 'purchases', 'inventory', 'movements', 'profitability', 'gst', 'payments', 'outstanding', 'returns', 'outlets', 'cashiers'])->name('show');
        Route::get('{report}/export', [ReportsController::class, 'export'])->whereIn('report', ['sales', 'purchases', 'inventory', 'movements', 'profitability', 'gst', 'payments', 'outstanding', 'returns', 'outlets', 'cashiers'])->middleware('can:crm.reports.export')->name('export');
    });
    Route::prefix('getting-started/store-setup')->name('onboarding.store-setup.')->group(function (): void {
        Route::get('/', [StoreSetupWizardController::class, 'show'])->name('show');
        Route::post('start', [StoreSetupWizardController::class, 'start'])->middleware('can:store.setup.manage')->name('start');
        Route::post('save', [StoreSetupWizardController::class, 'save'])->middleware('can:store.setup.manage')->name('save');
        Route::post('skip', [StoreSetupWizardController::class, 'skip'])->middleware('can:store.setup.manage')->name('skip');
        Route::post('apply', [StoreSetupWizardController::class, 'apply'])->middleware('can:store.setup.manage')->name('apply');
        Route::get('complete', [StoreSetupWizardController::class, 'complete'])->name('complete');
        Route::get('product-template', [StoreSetupWizardController::class, 'template'])->middleware('can:store.setup.manage')->name('template');
    });
    Route::post('settings/free365-onboarding/dismiss', [Free365OnboardingController::class, 'dismiss'])->name('free365-onboarding.dismiss');
    Route::get('modules/{module}', ModuleController::class)->name('modules.show');

    Route::middleware('role:administrator')->prefix('account/subscription')->name('account.subscription.')->group(function (): void {
        Route::get('/', [TenantSubscriptionController::class, 'index'])->middleware('can:subscription.view')->name('index');
        Route::post('requests', [TenantSubscriptionController::class, 'requestChange'])->middleware('can:subscription.request-plan-change')->name('requests.store');
        Route::get('billing', [TenantBillingController::class, 'index'])->middleware('can:subscription.billing.view')->name('billing.index');
        Route::get('billing/invoices/{invoice}', [TenantBillingController::class, 'show'])->middleware('can:subscription.billing.view')->name('billing.show');
        Route::get('billing/invoices/{invoice}/pdf', [TenantBillingController::class, 'pdf'])->middleware('can:subscription.billing.view')->name('billing.pdf');
        Route::post('billing/invoices/{invoice}/checkout', [TenantBillingController::class, 'checkout'])->middleware(['can:subscription.billing.pay', 'throttle:10,1'])->name('billing.checkout');
        Route::get('billing/invoices/{invoice}/checkout/{session}', [TenantBillingController::class, 'checkoutShow'])->middleware('can:subscription.billing.pay')->name('billing.checkout.show');
        Route::post('billing/invoices/{invoice}/checkout/{session}/callback', [TenantBillingController::class, 'callback'])->middleware(['can:subscription.billing.pay', 'throttle:10,1'])->name('billing.checkout.callback');
        Route::get('billing/invoices/{invoice}/receipts/{payment}', [TenantBillingController::class, 'receipt'])->middleware('can:subscription.billing.receipts.view')->name('billing.receipt');
        Route::get('white-label', [WhiteLabelController::class, 'edit'])->name('white-label.edit');
        Route::put('white-label', [WhiteLabelController::class, 'update'])->name('white-label.update');
    });

    Route::middleware('platform-admin')->prefix('saas')->name('saas.')->group(function (): void {
        Route::get('/', SaasDashboardController::class)->middleware('can:saas.dashboard.view')->name('dashboard');
        Route::get('billing', [SaasBillingController::class, 'index'])->middleware('can:saas.billing.view')->name('billing.index');
        Route::get('billing/invoices', [SaasBillingController::class, 'index'])->middleware('can:saas.billing.view')->name('billing.invoices.index');
        Route::get('billing/payments', [SaasBillingController::class, 'payments'])->middleware('can:saas.billing.view')->name('billing.payments.index');
        Route::get('billing/refunds', [SaasBillingController::class, 'refunds'])->middleware('can:saas.billing.refund')->name('billing.refunds.index');
        Route::get('billing/reconciliation', [SaasBillingController::class, 'reconciliation'])->middleware('can:saas.billing.reconcile')->name('billing.reconciliation.index');
        Route::get('billing/reports', [SaasBillingController::class, 'reports'])->middleware('can:saas.billing.view')->name('billing.reports');
        Route::get('billing/invoices/{invoice}', [SaasBillingController::class, 'show'])->middleware('can:saas.billing.view')->name('billing.show');
        Route::post('billing/invoices/{invoice}/issue', [SaasBillingController::class, 'issue'])->middleware('can:saas.billing.issue')->name('billing.issue');
        Route::post('billing/invoices/{invoice}/void', [SaasBillingController::class, 'void'])->middleware('can:saas.billing.void')->name('billing.void');
        Route::post('billing/invoices/{invoice}/payments', [SaasBillingController::class, 'payment'])->middleware('can:saas.billing.record-payment')->name('billing.payments.store');
        Route::get('billing/invoices/{invoice}/pdf', [SaasBillingController::class, 'pdf'])->middleware('can:saas.billing.view')->name('billing.pdf');
        Route::get('billing/invoices/{invoice}/receipts/{payment}', [SaasBillingController::class, 'receipt'])->middleware('can:saas.billing.view')->name('billing.receipt');
        Route::post('billing/payments/{payment}/refunds', [SaasBillingController::class, 'requestRefund'])->middleware('can:saas.billing.refund')->name('billing.refunds.store');
        Route::post('billing/refunds/{refund}/approve', [SaasBillingController::class, 'approveRefund'])->middleware('can:saas.billing.refund')->name('billing.refunds.approve');
        Route::get('billing/gateway', [SaasBillingGatewayController::class, 'index'])->middleware('can:saas.billing.gateway.manage')->name('billing.gateway.index');
        Route::put('billing/gateway', [SaasBillingGatewayController::class, 'update'])->middleware('can:saas.billing.gateway.manage')->name('billing.gateway.update');
        Route::post('billing/gateway/test', [SaasBillingGatewayController::class, 'test'])->middleware('can:saas.billing.gateway.manage')->name('billing.gateway.test');
        Route::get('plans', [SaasPlanController::class, 'index'])->middleware('can:saas.plans.view')->name('plans.index');
        Route::get('plans/create', [SaasPlanController::class, 'create'])->middleware('can:saas.plans.create')->name('plans.create');
        Route::post('plans', [SaasPlanController::class, 'store'])->middleware('can:saas.plans.create')->name('plans.store');
        Route::get('plans/{plan}', [SaasPlanController::class, 'show'])->middleware('can:saas.plans.view')->name('plans.show');
        Route::get('plans/{plan}/edit', [SaasPlanController::class, 'edit'])->middleware('can:saas.plans.update')->name('plans.edit');
        Route::put('plans/{plan}', [SaasPlanController::class, 'update'])->middleware('can:saas.plans.update')->name('plans.update');
        Route::post('plans/{plan}/duplicate', [SaasPlanController::class, 'duplicate'])->middleware('can:saas.plans.create')->name('plans.duplicate');
        Route::post('plans/{plan}/archive', [SaasPlanController::class, 'archive'])->middleware('can:saas.plans.archive')->name('plans.archive');
        Route::get('subscriptions', [SaasSubscriptionController::class, 'index'])->middleware('can:saas.subscriptions.view')->name('subscriptions.index');
        Route::post('subscriptions/{subscription}/transition', [SaasSubscriptionController::class, 'transition'])->middleware('can:saas.subscriptions.update')->name('subscriptions.transition');
        Route::post('subscriptions/{subscription}/renew', [SaasSubscriptionController::class, 'renew'])->middleware('can:saas.subscriptions.renew')->name('subscriptions.renew');
        Route::post('subscriptions/{subscription}/trials/extend', [SaasSubscriptionController::class, 'extendTrial'])->middleware('can:saas.trials.extend')->name('subscriptions.trials.extend');
        Route::post('subscriptions/{subscription}/plan-change', [SaasSubscriptionController::class, 'changePlan'])->middleware('can:saas.subscriptions.update')->name('subscriptions.plan-change');
        Route::delete('subscriptions/{subscription}/plan-change', [SaasSubscriptionController::class, 'cancelPlanChange'])->middleware('can:saas.subscriptions.update')->name('subscriptions.plan-change.cancel');
        Route::get('tenants/create', [SaasTenantOnboardingController::class, 'create'])->middleware('can:saas.tenants.create')->name('tenants.create');
        Route::post('tenants', [SaasTenantOnboardingController::class, 'store'])->middleware('can:saas.tenants.create')->name('tenants.store');
        Route::get('tenants/{company}', [SaasSubscriptionController::class, 'show'])->middleware('can:saas.tenants.view')->name('tenants.show');
        Route::get('onboarding', [SaasTenantOnboardingController::class, 'index'])->middleware('can:saas.onboarding.manage')->name('onboarding.index');
        Route::get('onboarding/create', [SaasTenantOnboardingController::class, 'create'])->middleware('can:saas.tenants.create')->name('onboarding.create');
        Route::post('onboarding', [SaasTenantOnboardingController::class, 'store'])->middleware('can:saas.onboarding.manage')->name('onboarding.store');
        Route::get('resellers', [SaasResellerController::class, 'index'])->middleware('can:saas.resellers.view')->name('resellers.index');
        Route::get('resellers/create', [SaasResellerController::class, 'create'])->middleware('can:saas.resellers.manage')->name('resellers.create');
        Route::post('resellers', [SaasResellerController::class, 'store'])->middleware('can:saas.resellers.manage')->name('resellers.store');
        Route::get('resellers/{reseller}', [SaasResellerController::class, 'show'])->middleware('can:saas.resellers.view')->name('resellers.show');
        Route::get('resellers/{reseller}/edit', [SaasResellerController::class, 'edit'])->middleware('can:saas.resellers.manage')->name('resellers.edit');
        Route::put('resellers/{reseller}', [SaasResellerController::class, 'update'])->middleware('can:saas.resellers.manage')->name('resellers.update');
        Route::post('resellers/{reseller}/tenants', [SaasResellerController::class, 'assign'])->middleware('can:saas.resellers.manage')->name('resellers.tenants.assign');
        Route::delete('resellers/{reseller}/tenants/{assignment}', [SaasResellerController::class, 'unassign'])->middleware('can:saas.resellers.manage')->name('resellers.tenants.unassign');
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

    Route::prefix('ai')->name('ai.')->group(function (): void {
        Route::get('/', [AiForecastController::class, 'index'])->middleware('can:ai.dashboard.view')->name('dashboard');
        Route::post('ask', [AiForecastController::class, 'ask'])->middleware(['can:ai.dashboard.view', 'throttle:ai-assistant'])->name('ask');
        Route::post('run', [AiForecastController::class, 'run'])->middleware(['can:ai.forecasts.run', 'throttle:ai-forecast-run'])->name('run');
        Route::post('insights/{insight}/review', [AiForecastController::class, 'review'])->middleware('can:ai.insights.review')->name('insights.review');
        Route::get('settings', [AiForecastController::class, 'settings'])->middleware('can:ai.settings.manage')->name('settings');
        Route::put('settings', [AiForecastController::class, 'updateSettings'])->middleware('can:ai.settings.manage')->name('settings.update');
    });

    Route::middleware(['role:administrator,manager,sales', 'can:crm.view'])->prefix('crm')->name('crm.')->group(function (): void {
        Route::get('/', CrmDashboardController::class)->name('dashboard');
        Route::prefix('settings')->middleware('can:crm.settings.manage')->name('settings.')->group(function (): void {
            Route::get('/', [LeadMasterDataController::class, 'index'])->name('index');

            Route::get('lead-statuses', [LeadMasterDataController::class, 'statuses'])->name('statuses.index');
            Route::post('lead-statuses', [LeadMasterDataController::class, 'storeStatus'])->name('statuses.store');
            Route::patch('lead-statuses/reorder', [LeadMasterDataController::class, 'reorderStatuses'])->name('statuses.reorder');
            Route::post('lead-statuses/{status}/move/{direction}', [LeadMasterDataController::class, 'moveStatus'])->whereIn('direction', ['up', 'down'])->name('statuses.move');
            Route::get('lead-statuses/{status}/edit', [LeadMasterDataController::class, 'editStatus'])->name('statuses.edit');
            Route::put('lead-statuses/{status}', [LeadMasterDataController::class, 'updateStatus'])->name('statuses.update');
            Route::post('lead-statuses/{status}/toggle', [LeadMasterDataController::class, 'toggleStatus'])->name('statuses.toggle');
            Route::post('lead-statuses/{status}/default', [LeadMasterDataController::class, 'defaultStatus'])->name('statuses.default');
            Route::delete('lead-statuses/{status}', [LeadMasterDataController::class, 'destroyStatus'])->name('statuses.destroy');

            Route::get('lead-sources', [LeadMasterDataController::class, 'sources'])->name('sources.index');
            Route::post('lead-sources', [LeadMasterDataController::class, 'storeSource'])->name('sources.store');
            Route::patch('lead-sources/reorder', [LeadMasterDataController::class, 'reorderSources'])->name('sources.reorder');
            Route::post('lead-sources/{source}/move/{direction}', [LeadMasterDataController::class, 'moveSource'])->whereIn('direction', ['up', 'down'])->name('sources.move');
            Route::get('lead-sources/{source}/edit', [LeadMasterDataController::class, 'editSource'])->name('sources.edit');
            Route::put('lead-sources/{source}', [LeadMasterDataController::class, 'updateSource'])->name('sources.update');
            Route::post('lead-sources/{source}/toggle', [LeadMasterDataController::class, 'toggleSource'])->name('sources.toggle');
            Route::post('lead-sources/{source}/default', [LeadMasterDataController::class, 'defaultSource'])->name('sources.default');
            Route::delete('lead-sources/{source}', [LeadMasterDataController::class, 'destroySource'])->name('sources.destroy');
        });

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
        Route::get('proforma-invoices/create', [ProformaController::class, 'create'])->middleware('can:crm.proformas.create')->name('proformas.create');
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
        Route::get('invoices/designs', [InvoiceTemplateController::class, 'index'])->middleware('can:sales.invoices.view')->name('invoices.templates.index');
        Route::put('invoices/designs', [InvoiceTemplateController::class, 'update'])->middleware('can:sales.invoices.update')->name('invoices.templates.update');
        Route::get('invoices/designs/preview/{invoice}', [InvoiceTemplateController::class, 'preview'])->middleware('can:sales.invoices.view')->name('invoices.templates.preview');
        Route::get('invoices/document-settings', [SalesDocumentSettingsController::class, 'index'])->middleware('can:sales.invoices.update')->name('invoices.document-settings.index');
        Route::put('invoices/document-settings', [SalesDocumentSettingsController::class, 'update'])->middleware('can:sales.invoices.update')->name('invoices.document-settings.update');
        Route::get('invoices/reminders/settings', [InvoiceReminderSettingsController::class, 'index'])->middleware('can:sales.reminders.manage')->name('invoices.reminders.settings');
        Route::put('invoices/reminders/settings', [InvoiceReminderSettingsController::class, 'update'])->middleware('can:sales.reminders.manage')->name('invoices.reminders.settings.update');
        Route::post('invoices/reminders/settings/restore-defaults', [InvoiceReminderSettingsController::class, 'restore'])->middleware('can:sales.reminders.manage')->name('invoices.reminders.settings.restore');
        Route::get('invoices/customers/search', [InvoiceController::class, 'customers'])->middleware('can:sales.invoices.create')->name('invoices.customers.search');
        Route::get('invoices/products/search', [InvoiceController::class, 'products'])->middleware('can:sales.invoices.create')->name('invoices.products.search');
        Route::get('invoices/amendments/products/search', [InvoiceController::class, 'products'])->middleware('can:sales.invoices.amend')->name('invoices.amendments.products.search');
        Route::post('invoices/customers', [InvoiceController::class, 'quickCustomer'])->middleware(['can:sales.invoices.create', 'can:crm.customers.create', 'throttle:20,1'])->name('invoices.customers.store');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->middleware('can:sales.invoices.create')->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware('can:sales.invoices.create')->name('invoices.store');
        Route::get('invoices/export', [InvoiceController::class, 'export'])->middleware('can:sales.finance.export')->name('invoices.export');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('can:sales.invoices.update')->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('can:sales.invoices.update')->name('invoices.update');
        Route::get('quotations/{quotation}/invoices/create', [InvoiceController::class, 'createFromQuotation'])->middleware('can:sales.invoices.create')->name('invoices.create-from-quotation');
        Route::post('quotations/{quotation}/invoices', [InvoiceController::class, 'storeFromQuotation'])->middleware('can:sales.invoices.create')->name('invoices.store-from-quotation');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('can:sales.invoices.view')->name('invoices.show');
        Route::get('invoices/{invoice}/amend', [InvoiceAmendmentController::class, 'create'])->middleware('can:sales.invoices.amend')->name('invoices.amendments.create');
        Route::post('invoices/{invoice}/amendments', [InvoiceAmendmentController::class, 'store'])->middleware(['can:sales.invoices.amend', 'throttle:10,1'])->name('invoices.amendments.store');
        Route::get('invoices/{invoice}/returns/create', [CrmInvoiceReturnController::class, 'create'])->middleware('can:sales.returns.create')->name('invoices.returns.create');
        Route::post('invoices/{invoice}/returns', [CrmInvoiceReturnController::class, 'store'])->middleware(['can:sales.returns.finalize', 'throttle:10,1'])->name('invoices.returns.store');
        Route::get('credit-notes/{return}', [CrmInvoiceReturnController::class, 'show'])->middleware('can:sales.returns.view')->name('credit-notes.show');
        Route::get('credit-notes/{return}/print', [CrmInvoiceReturnController::class, 'print'])->middleware('can:sales.credit_notes.pdf')->name('credit-notes.print');
        Route::get('credit-notes/{return}/pdf', [CrmInvoiceReturnController::class, 'pdf'])->middleware('can:sales.credit_notes.pdf')->name('credit-notes.pdf');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('can:sales.invoices.issue')->name('invoices.issue');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->middleware('can:sales.payments.record')->name('invoices.payments.store');
        Route::post('invoices/{invoice}/payments/{payment}/clear', [InvoiceController::class, 'clear'])->middleware('can:sales.payments.clear')->name('invoices.payments.clear');
        Route::post('invoices/{invoice}/payments/{payment}/reverse', [InvoiceController::class, 'reverse'])->middleware('can:sales.payments.reverse')->name('invoices.payments.reverse');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('can:sales.invoices.cancel')->name('invoices.cancel');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->middleware('can:sales.invoices.pdf')->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('can:sales.invoices.pdf')->name('invoices.pdf');
        Route::get('invoices/{invoice}/receipts/{payment}', [InvoiceController::class, 'receipt'])->middleware('can:sales.receipts.pdf')->name('invoices.receipts.pdf');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('can:sales.invoices.send')->name('invoices.send');
        Route::post('invoices/{invoice}/email-deliveries/{delivery}/resend', [InvoiceController::class, 'resend'])->middleware('can:sales.invoices.send')->name('invoices.email-deliveries.resend');
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
        Route::post('favorites/{product}', [PosController::class, 'toggleFavorite'])->whereNumber('product')->name('favorites.toggle');
        Route::get('sales', [PosController::class, 'salesHistory'])->middleware('can:pos.sales.view')->name('sales.index');
        Route::get('returns', [PosReturnController::class, 'index'])->middleware('can:pos.returns.view')->name('returns.index');
        Route::get('returns/create', [PosReturnController::class, 'create'])->middleware('can:pos.returns.create')->name('returns.create');
        Route::post('returns/preview', [PosReturnController::class, 'preview'])->middleware('can:pos.returns.create')->name('returns.preview');
        Route::post('returns', [PosReturnController::class, 'store'])->middleware('can:pos.returns.create')->name('returns.store');
        Route::get('returns/settings', [PosReturnController::class, 'settings'])->middleware('can:pos.returns.settings.manage')->name('returns.settings');
        Route::put('returns/settings', [PosReturnController::class, 'updateSettings'])->middleware('can:pos.returns.settings.manage')->name('returns.settings.update');
        Route::get('returns/{posReturn}', [PosReturnController::class, 'show'])->whereNumber('posReturn')->middleware('can:pos.returns.view')->name('returns.show');
        Route::post('returns/{posReturn}/approve', [PosReturnController::class, 'approve'])->whereNumber('posReturn')->middleware('can:pos.returns.approve')->name('returns.approve');
        Route::post('returns/{posReturn}/reject', [PosReturnController::class, 'reject'])->whereNumber('posReturn')->middleware('can:pos.returns.approve')->name('returns.reject');
        Route::post('returns/{posReturn}/cancel', [PosReturnController::class, 'cancel'])->whereNumber('posReturn')->middleware('can:pos.returns.cancel')->name('returns.cancel');
        Route::post('returns/{posReturn}/complete', [PosReturnController::class, 'complete'])->whereNumber('posReturn')->middleware('can:pos.returns.complete')->name('returns.complete');
        Route::get('returns/{posReturn}/pdf', [PosReturnController::class, 'pdf'])->whereNumber('posReturn')->middleware('can:pos.returns.reprint')->name('returns.pdf');
        Route::get('catalog', [PosController::class, 'catalog'])->name('catalog');
        Route::get('customers/lookup', [PosController::class, 'customer'])->name('customers.lookup');
        Route::post('customers/quick-create', [PosController::class, 'quickCustomer'])->middleware('can:pos.customers.create')->name('customers.quick-create');
        Route::post('hold', [PosController::class, 'hold'])->middleware('can:pos.hold')->name('hold');
        Route::post('checkout', [PosController::class, 'complete'])->middleware(['can:pos.checkout', 'can:pos.payments.record'])->name('checkout');
        Route::get('held/{sale}', [PosController::class, 'resume'])->whereNumber('sale')->middleware('can:pos.sales.resume')->name('held.resume');
        Route::delete('held/{sale}', [PosController::class, 'destroyHeld'])->whereNumber('sale')->middleware('can:pos.hold')->name('held.destroy');
        Route::get('receipts/{sale}', [PosController::class, 'receipt'])->whereNumber('sale')->name('receipts.show');
        Route::get('receipts/{sale}/pdf', [PosController::class, 'receiptPdf'])->whereNumber('sale')->middleware(['can:pos.receipts.view', 'can:pos.receipts.print'])->name('receipts.pdf');
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

        Route::get('automation', [NotificationAutomationController::class, 'edit'])->middleware('can:automation.manage')->name('automation.edit');
        Route::put('automation', [NotificationAutomationController::class, 'update'])->middleware('can:automation.manage')->name('automation.update');

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

    Route::middleware(['role:administrator,manager,sales,staff', 'can:inventory.view'])->prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/', InventoryDashboardController::class)->name('dashboard');
        Route::get('decision-dashboard', [InventoryIntelligenceController::class, 'index'])->middleware('can:inventory.decision_dashboard.view')->name('decision-dashboard');
        Route::get('intelligence', [InventoryIntelligenceController::class, 'index'])->middleware('can:inventory.decision_dashboard.view')->name('intelligence.index');
        Route::get('intelligence/export/{dataset}', [InventoryIntelligenceController::class, 'export'])->middleware(['can:inventory.decision_dashboard.view', 'can:inventory.reports.export'])->name('intelligence.export');

        Route::get('products', [ProductController::class, 'index'])->middleware('can:inventory.products.view')->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->middleware('can:inventory.products.create')->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->middleware('can:inventory.products.create')->name('products.store');
        Route::get('products/{product}/image', [ProductController::class, 'image'])->whereNumber('product')->middleware('can:inventory.products.view')->name('products.image');
        Route::delete('products/{product}/image', [ProductController::class, 'destroyImage'])->whereNumber('product')->middleware('can:inventory.products.image.manage')->name('products.image.destroy');
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
        Route::get('stock-availability', [StockAvailabilityController::class, 'index'])->middleware('can:inventory.stock.view')->name('stock.availability');
        Route::get('stock-availability/{product}', [StockAvailabilityController::class, 'show'])->middleware('can:inventory.stock.view')->name('stock.product');
        Route::get('reports/{report?}', [InventoryReportController::class, 'show'])->middleware('can:inventory.stock.view')->name('reports.show');
        Route::get('reports/{report}/export', [InventoryReportController::class, 'export'])->middleware('can:inventory.reports.export')->name('reports.export');
        Route::get('transfers', [StockTransferController::class, 'index'])->middleware('can:inventory.transfers.view')->name('transfers.index');
        Route::get('transfers/create', [StockTransferController::class, 'create'])->middleware('can:inventory.transfers.create')->name('transfers.create');
        Route::get('transfers/product-search', [StockTransferController::class, 'products'])->middleware('can:inventory.transfers.create')->name('transfers.products');
        Route::post('transfers', [StockTransferController::class, 'store'])->middleware('can:inventory.transfers.create')->name('transfers.store');
        Route::get('transfers/{transfer}', [StockTransferController::class, 'show'])->middleware('can:inventory.transfers.view')->name('transfers.show');
        Route::get('transfers/{transfer}/print', [StockTransferController::class, 'printDocument'])->middleware('can:inventory.transfers.view')->name('transfers.print');
        Route::post('transfers/{transfer}/submit', [StockTransferController::class, 'submit'])->middleware('can:inventory.transfers.create')->name('transfers.submit');
        Route::post('transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->middleware('can:inventory.transfers.approve')->name('transfers.approve');
        Route::post('transfers/{transfer}/reject', [StockTransferController::class, 'reject'])->middleware('can:inventory.transfers.approve')->name('transfers.reject');
        Route::post('transfers/{transfer}/pack', [StockTransferController::class, 'pack'])->middleware('can:inventory.transfers.pack')->name('transfers.pack');
        Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->middleware('can:inventory.transfers.dispatch')->name('transfers.dispatch');
        Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->middleware('can:inventory.transfers.receive')->name('transfers.receive');
        Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('can:inventory.transfers.cancel')->name('transfers.cancel');
        Route::post('transfers/{transfer}/discrepancies', [StockTransferController::class, 'reportDiscrepancy'])->middleware('can:inventory.transfers.receive')->name('transfers.discrepancies.store');
        Route::post('transfer-discrepancies/{discrepancy}/resolve', [StockTransferController::class, 'resolve'])->middleware('can:inventory.transfers.resolve_discrepancy')->name('transfers.discrepancies.resolve');
        Route::get('opening-stock', [OpeningStockController::class, 'create'])->middleware('can:inventory.stock.opening')->name('opening-stock.create');
        Route::post('opening-stock', [OpeningStockController::class, 'store'])->middleware('can:inventory.stock.opening')->name('opening-stock.store');
        Route::get('adjustments', [StockAdjustmentController::class, 'index'])->middleware('can:inventory.stock.adjust')->name('adjustments.index');
        Route::get('adjustments/create', [StockAdjustmentController::class, 'create'])->middleware('can:inventory.stock.adjust')->name('adjustments.create');
        Route::post('adjustments', [StockAdjustmentController::class, 'store'])->middleware('can:inventory.stock.adjust')->name('adjustments.store');
        Route::get('adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->middleware('can:inventory.stock.adjust')->name('adjustments.show');
        Route::post('adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->middleware('can:inventory.stock.approve_adjustment')->name('adjustments.approve');
        Route::get('counts', [StockCountController::class, 'index'])->middleware('can:inventory.counts.view')->name('counts.index');
        Route::get('counts/create', [StockCountController::class, 'create'])->middleware('can:inventory.counts.create')->name('counts.create');
        Route::post('counts', [StockCountController::class, 'store'])->middleware('can:inventory.counts.create')->name('counts.store');
        Route::get('counts/{count}', [StockCountController::class, 'show'])->middleware('can:inventory.counts.view')->name('counts.show');
        Route::put('counts/{count}', [StockCountController::class, 'save'])->middleware('can:inventory.counts.create')->name('counts.save');
        Route::post('counts/{count}/submit', [StockCountController::class, 'submit'])->middleware('can:inventory.counts.submit')->name('counts.submit');
        Route::post('counts/{count}/approve', [StockCountController::class, 'approve'])->middleware('can:inventory.counts.approve')->name('counts.approve');
        Route::post('counts/{count}/post', [StockCountController::class, 'post'])->middleware('can:inventory.counts.post')->name('counts.post');
        Route::get('traceability', [InventoryTraceabilityController::class, 'index'])->middleware('can:inventory.batch.view')->name('traceability.index');
        Route::post('traceability/batches', [InventoryTraceabilityController::class, 'storeBatch'])->middleware('can:inventory.batch.manage')->name('traceability.batches.store');
        Route::post('traceability/serials', [InventoryTraceabilityController::class, 'storeSerials'])->middleware('can:inventory.serial.manage')->name('traceability.serials.store');
        Route::put('traceability/serials/{serial}', [InventoryTraceabilityController::class, 'updateSerial'])->middleware('can:inventory.serial.manage')->name('traceability.serials.update');

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
        Route::post('requests/{purchaseRequest}/duplicate', [PurchaseRequestController::class, 'duplicate'])->middleware('can:purchases.requests.create')->name('requests.duplicate');
        Route::post('requests/{purchaseRequest}/convert', [PurchaseRequestController::class, 'convert'])->middleware('can:purchases.requests.convert')->name('requests.convert');

        Route::get('orders', [PurchaseOrderController::class, 'index'])->middleware('can:purchases.orders.view')->name('orders.index');
        Route::get('orders/create', [PurchaseOrderController::class, 'create'])->middleware('can:purchases.orders.create')->name('orders.create');
        Route::post('orders', [PurchaseOrderController::class, 'store'])->middleware('can:purchases.orders.create')->name('orders.store');
        Route::get('orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('can:purchases.orders.view')->name('orders.show');
        Route::get('orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->middleware('can:purchases.orders.view')->name('orders.print');
        Route::post('orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('can:purchases.orders.update')->name('orders.submit');
        Route::post('orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('can:purchases.orders.approve')->name('orders.approve');
        Route::post('orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('can:purchases.orders.send')->name('orders.send');
        Route::post('orders/{purchaseOrder}/supplier-confirm', [PurchaseOrderController::class, 'supplierConfirm'])->middleware('can:purchases.orders.update')->name('orders.supplier-confirm');
        Route::post('orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('can:purchases.orders.cancel')->name('orders.cancel');

        Route::get('grn', [GoodsReceiptController::class, 'index'])->middleware('can:purchases.grn.view')->name('grn.index');
        Route::get('grn/create', [GoodsReceiptController::class, 'create'])->middleware('can:purchases.grn.create')->name('grn.create');
        Route::post('grn', [GoodsReceiptController::class, 'store'])->middleware('can:purchases.grn.create')->name('grn.store');
        Route::get('grn/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('can:purchases.grn.view')->name('grn.show');
        Route::post('grn/{goodsReceipt}/receive', [GoodsReceiptController::class, 'receive'])->middleware('can:purchases.grn.receive')->name('grn.receive');

        Route::get('invoices', [PurchaseInvoiceController::class, 'index'])->middleware('can:purchase-invoices.view')->name('invoices.index');
        Route::get('invoices/create', [PurchaseInvoiceController::class, 'create'])->middleware('can:purchase-invoices.create')->name('invoices.create');
        Route::post('invoices', [PurchaseInvoiceController::class, 'store'])->middleware('can:purchase-invoices.create')->name('invoices.store');
        Route::get('invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->middleware('can:purchase-invoices.view')->name('invoices.show');
        Route::post('invoices/{purchaseInvoice}/verify', [PurchaseInvoiceController::class, 'verify'])->middleware('can:purchase-invoices.verify')->name('invoices.verify');
        Route::post('invoices/{purchaseInvoice}/approve', [PurchaseInvoiceController::class, 'approve'])->middleware('can:purchase-invoices.approve')->name('invoices.approve');
        Route::post('invoices/{purchaseInvoice}/cancel', [PurchaseInvoiceController::class, 'cancel'])->middleware('can:purchase-invoices.cancel')->name('invoices.cancel');
        Route::post('invoices/{purchaseInvoice}/match-exceptions/{exception}/resolve', [PurchaseInvoiceController::class, 'resolveMatchException'])->middleware('can:purchase-invoices.approve')->name('invoices.match-exceptions.resolve');

        Route::get('payments', [SupplierPaymentController::class, 'index'])->middleware('can:supplier-payments.view')->name('payments.index');
        Route::get('payments/create', [SupplierPaymentController::class, 'create'])->middleware('can:supplier-payments.create')->name('payments.create');
        Route::post('payments', [SupplierPaymentController::class, 'store'])->middleware('can:supplier-payments.create')->name('payments.store');
        Route::get('payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->middleware('can:supplier-payments.view')->name('payments.show');
        Route::post('payments/{supplierPayment}/reverse', [SupplierPaymentController::class, 'reverse'])->middleware('can:supplier-payments.reverse')->name('payments.reverse');

        Route::get('reports', [PurchaseReportController::class, 'index'])->middleware('can:purchase-reports.view')->name('reports.index');
        Route::get('reports/input-gst', [PurchaseReportController::class, 'inputGst'])->middleware('can:input-gst-reports.view')->name('reports.input-gst');

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
    Route::get('workforce/me', [WorkforceController::class, 'self'])->middleware('can:workforce.self.view')->name('workforce.self');
    Route::middleware(['role:administrator,manager', 'can:workforce.view'])->prefix('workforce')->name('workforce.')->group(function (): void {
        Route::get('/', [WorkforceController::class, 'dashboard'])->name('dashboard');
        Route::get('employees', [WorkforceController::class, 'employees'])->name('employees.index');
        Route::get('employees/export', [WorkforceController::class, 'export'])->middleware('can:workforce.export')->name('employees.export');
        Route::get('employees/create', [WorkforceController::class, 'createEmployee'])->middleware('can:workforce.manage')->name('employees.create');
        Route::post('employees', [WorkforceController::class, 'storeEmployee'])->middleware('can:workforce.manage')->name('employees.store');
        Route::get('employees/{employee}', [WorkforceController::class, 'showEmployee'])->name('employees.show');
        Route::get('employees/{employee}/edit', [WorkforceController::class, 'editEmployee'])->middleware('can:workforce.manage')->name('employees.edit');
        Route::put('employees/{employee}', [WorkforceController::class, 'updateEmployee'])->middleware('can:workforce.manage')->name('employees.update');
        Route::post('employees/{employee}/archive', [WorkforceController::class, 'archiveEmployee'])->middleware('can:workforce.manage')->name('employees.archive');
        Route::get('employees/{employee}/user/create', [WorkforceController::class, 'createUser'])->middleware('can:workforce.manage')->name('users.create');
        Route::post('employees/{employee}/user', [WorkforceController::class, 'storeUser'])->middleware('can:workforce.manage')->name('users.store');
        Route::post('employees/{employee}/invite', [WorkforceController::class, 'invite'])->middleware(['can:workforce.manage', 'throttle:workforce-invitation'])->name('invitations.store');
        Route::post('invitations/{invitation}/cancel', [WorkforceController::class, 'cancelInvitation'])->middleware('can:workforce.manage')->name('invitations.cancel');
        Route::post('employees/{employee}/reviews', [WorkforceController::class, 'storeReview'])->middleware('can:workforce.reviews.manage')->name('reviews.store');
        Route::post('employees/{employee}/recognition', [WorkforceController::class, 'storeRecognition'])->middleware('can:workforce.recognition.manage')->name('recognitions.store');
        Route::get('users', [WorkforceController::class, 'accounts'])->middleware('can:workforce.manage')->name('users.index');
        Route::post('users/{user}/state', [WorkforceController::class, 'state'])->middleware('can:workforce.manage')->name('users.state');
        Route::post('users/{user}/role', [WorkforceController::class, 'assignRole'])->middleware('can:workforce.manage')->name('users.role');
        Route::get('roles', [WorkforceController::class, 'roles'])->middleware('can:workforce.manage')->name('roles.index');
        Route::post('roles', [WorkforceController::class, 'storeRole'])->middleware('can:workforce.manage')->name('roles.store');
    });
    Route::middleware(['role:administrator,manager', 'can:attendance.view_team'])->prefix('attendance')->name('attendance.')->group(function (): void {
        Route::get('dashboard', [AttendanceController::class, 'dashboard'])->name('dashboard');
        Route::get('records', [AttendanceController::class, 'index'])->name('index');
        Route::get('reviews', [AttendanceController::class, 'reviews'])->name('reviews');
        Route::post('employees/{employee}/check-in', [AttendanceController::class, 'managerCheckIn'])->middleware('can:attendance.manage_team')->name('manager.check-in');
        Route::post('records/{attendance}/manager-check-out', [AttendanceController::class, 'managerCheckOut'])->middleware('can:attendance.manage_team')->name('manager.check-out');
        Route::post('corrections/{correction}/review', [AttendanceController::class, 'reviewCorrection'])->middleware('can:attendance.review_corrections')->name('corrections.review');
        Route::post('overtime/{review}/review', [AttendanceController::class, 'reviewOvertime'])->middleware('can:overtime.review')->name('overtime.review');
        Route::get('export', [AttendanceController::class, 'export'])->middleware('can:attendance.export')->name('export');
        Route::get('summary', [AttendanceController::class, 'summary'])->name('summary');
        Route::get('roster', [RosterController::class, 'index'])->middleware('can:shifts.view_team')->name('roster');
        Route::post('shifts', [RosterController::class, 'storeShift'])->middleware('can:shifts.manage')->name('shifts.store');
        Route::post('assignments', [RosterController::class, 'assign'])->middleware('can:rosters.manage')->name('assignments.store');
        Route::post('roster/publish', [RosterController::class, 'publish'])->middleware('can:rosters.manage')->name('roster.publish');
        Route::get('leave/approvals', [LeaveController::class, 'approvals'])->middleware('can:leave.view_team')->name('leave.approvals');
        Route::post('leave/{leave}/review', [LeaveController::class, 'review'])->middleware('can:leave.approve')->name('leave.review');
        Route::post('leave/types', [LeaveController::class, 'storeType'])->middleware('can:leave.manage_policies')->name('leave.types.store');
        Route::post('leave/balances/{employee}', [LeaveController::class, 'adjustBalance'])->middleware('can:leave.adjust_balances')->name('leave.balances.adjust');
        Route::get('calendar-settings', [AttendanceCalendarController::class, 'index'])->middleware('can:holidays.manage')->name('calendar-settings');
        Route::post('holidays', [AttendanceCalendarController::class, 'storeHoliday'])->middleware('can:holidays.manage')->name('holidays.store');
        Route::post('weekly-offs', [AttendanceCalendarController::class, 'storeWeeklyOff'])->middleware('can:holidays.manage')->name('weekly-offs.store');
    });
    Route::middleware('role:administrator,manager')->group(function (): void {
        Route::get('settings/outlets', [OutletController::class, 'index'])->middleware('can:outlets.manage')->name('settings.outlets.index');
        Route::get('settings/outlets/create', [OutletController::class, 'create'])->middleware('can:outlets.manage')->name('settings.outlets.create');
        Route::post('settings/outlets', [OutletController::class, 'store'])->middleware('can:outlets.manage')->name('settings.outlets.store');
        Route::get('settings/outlets/{outlet}/edit', [OutletController::class, 'edit'])->middleware('can:outlets.manage')->name('settings.outlets.edit');
        Route::put('settings/outlets/{outlet}', [OutletController::class, 'update'])->middleware('can:outlets.manage')->name('settings.outlets.update');
        Route::post('settings/outlets/{outlet}/archive', [OutletController::class, 'archive'])->middleware('can:outlets.manage')->name('settings.outlets.archive');
        Route::post('settings/outlets/{outlet}/restore', [OutletController::class, 'restore'])->middleware('can:outlets.manage')->name('settings.outlets.restore');
        Route::post('settings/outlets/{outlet}/make-default', [OutletController::class, 'makeDefault'])->middleware('can:outlets.manage')->name('settings.outlets.make-default');
        Route::post('settings/outlets/{outlet}/assignments', [OutletController::class, 'assign'])->middleware('can:outlets.assign')->name('settings.outlets.assignments.store');
        Route::get('settings/company-profile', [CompanyProfileController::class, 'edit'])->middleware('can:company.profile.update')->name('settings.company-profile.edit');
        Route::put('settings/company-profile', [CompanyProfileController::class, 'update'])->middleware('can:company.profile.update')->name('settings.company-profile.update');
        Route::get('settings/{section}', [SettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('getting-started/outlets', [OutletController::class, 'setup'])->middleware('can:outlets.manage')->name('onboarding.outlets.show');
    Route::post('outlet-context', [OutletController::class, 'switch'])->middleware('can:outlets.context.switch')->name('outlet-context.switch');
});

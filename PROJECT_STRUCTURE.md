# RetailPOS Platform - Phase 1 / 1.5 / 1.6 / 2 / 3 Project Structure

This document describes the Command Center foundation built in Phase 1, the dynamic Module Registry foundation added in Phase 1.5, the Enterprise CMS foundation added in Phase 1.6, and the Enterprise CRM foundation added in Phase 2.

## Folder Structure

Phase 1 added the following application structure:

```text
app/
  Enums/
    UserRole.php
  Http/
    Controllers/
      Auth/
        AuthenticatedSessionController.php
        NewPasswordController.php
        PasswordResetLinkController.php
      CommandCenter/
        DashboardController.php
        ModuleController.php
        SettingsController.php
    Middleware/
      EnsureUserHasRole.php
    Requests/
      Auth/
        ForgotPasswordRequest.php
        LoginRequest.php
        ResetPasswordRequest.php
      Settings/
        UpdateSettingsRequest.php
  Models/
    Concerns/
      Auditable.php
    AuditLog.php
    Branch.php
    Company.php
    DashboardStatistic.php
    Setting.php
    User.php
  Repositories/
    DashboardRepository.php
    SettingsRepository.php
  Services/
    AuditLogger.php
  Support/
    Modules/
      Module.php
      ModuleRegistry.php
      ModuleServiceProvider.php

config/
  command-center.php
  modules.php

database/
  factories/
    BranchFactory.php
    CompanyFactory.php
    UserFactory.php
  migrations/
    2026_07_10_000001_create_companies_table.php
    2026_07_10_000002_create_branches_table.php
    2026_07_10_000003_add_command_center_columns_to_users_table.php
    2026_07_10_000004_create_dashboard_statistics_table.php
    2026_07_10_000005_create_settings_table.php
    2026_07_10_000006_create_audit_logs_table.php
  seeders/
    DatabaseSeeder.php

resources/
  css/
    app.css
  js/
    app.js
  views/
    auth/
      forgot-password.blade.php
      login.blade.php
      reset-password.blade.php
    command-center/
      dashboard.blade.php
      modules/
        show.blade.php
      settings/
        show.blade.php
    components/
      icon.blade.php
    layouts/
      admin.blade.php
      guest.blade.php

routes/
  web.php

tests/
  Feature/
    ExampleTest.php
    ModuleRegistryTest.php
  TestCase.php
```

## Models

- `User`: Extends Laravel's authenticatable user model. Phase 1 adds `company_id`, `branch_id`, `role`, `is_active`, and `last_login_at`, plus relationships to `Company` and `Branch`.
- `Company`: Represents the future SaaS tenant boundary. Owns branches, users, settings, and dashboard statistics.
- `Branch`: Represents a company operating location. Belongs to a company and can have many users.
- `DashboardStatistic`: Stores seeded dashboard widget values per company.
- `Setting`: Stores company-scoped settings by group and key, with JSON values.
- `AuditLog`: Stores login, logout, CRUD-style, and settings-change audit entries.
- `UserRole`: Enum for `administrator`, `manager`, and `staff`.
- `Auditable`: Model concern that records created, updated, and deleted events through `AuditLogger`.
- `Module`: Immutable value object for one registered Command Center module.

## Controllers

Authentication controllers:

- `AuthenticatedSessionController`
  - Shows login form.
  - Authenticates active users.
  - Supports remember-me.
  - Logs users out and invalidates the session.
- `PasswordResetLinkController`
  - Shows forgot password form.
  - Sends Laravel password reset links through the configured password broker.
- `NewPasswordController`
  - Shows reset password form.
  - Resets passwords through Laravel's password broker.

Command Center controllers:

- `DashboardController`
  - Loads dashboard metrics through `DashboardRepository`.
  - Loads recent audit log activity for the user's company.
- `ModuleController`
  - Serves registered modules from `ModuleRegistry`.
  - Rejects disabled modules with HTTP 404.
  - Rejects modules that do not allow the authenticated user's role with HTTP 403.
  - Provides the audit-log table view for the `audit-logs` module.
- `SettingsController`
  - Shows settings sections.
  - Saves settings through `SettingsRepository`.
  - Records `settings.updated` audit entries.

## Middleware

- `EnsureUserHasRole`
  - Registered as the `role` route middleware alias in `bootstrap/app.php`.
  - Accepts one or more allowed roles.
  - Aborts with HTTP 403 when the authenticated user does not match an allowed role.

Current usage:

```php
Route::middleware('role:administrator,manager')->group(function (): void {
    Route::get('settings/{section}', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings/{section}', [SettingsController::class, 'update'])->name('settings.update');
});
```

## Routes

Defined in `routes/web.php`.

Public entry:

- `GET /`
  - Redirects authenticated users to `dashboard`.
  - Redirects guests to `login`.

Guest routes:

- `GET /login` named `login`
- `POST /login`
- `GET /forgot-password` named `password.request`
- `POST /forgot-password` named `password.email`
- `GET /reset-password/{token}` named `password.reset`
- `POST /reset-password` named `password.store`

Authenticated routes:

- `POST /logout` named `logout`
- `GET /dashboard` named `dashboard`
- `GET /modules/{module}` named `modules.show`
- `ANY /settings` named `settings.index`, redirects to `/settings/general`

Role-protected settings routes:

- `GET /settings/{section}` named `settings.show`
- `PUT /settings/{section}` named `settings.update`

## Seeders

`DatabaseSeeder` is idempotent and uses `updateOrCreate` for demo data.

Seeded demo company:

- Name: `Crystal Retail Demo`
- Legal name: `Crystal Retail Demo Private Limited`
- Tax ID: `GSTIN29ABCDE1234F1Z5`
- Timezone: `Asia/Kolkata`
- Currency: `INR`

Seeded demo branch:

- Name: `Bengaluru HQ`
- Code: `BLR-HQ`
- Primary branch: yes

Seeded admin user:

- Email: `admin@retailpos.test`
- Password: `password`
- Role: `Administrator`
- Company: `Crystal Retail Demo`
- Branch: `Bengaluru HQ`

Seeded supporting data:

- Dashboard statistics for all Phase 1 dashboard cards.
- Settings for all Phase 1 settings sections.

## Database Tables

Existing Laravel tables:

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Phase 1 tables:

- `companies`
  - Stores company identity, contact, address, timezone, currency, and active status.
- `branches`
  - Stores branch identity, company relationship, branch code, contact details, location, primary flag, and active status.
- `dashboard_statistics`
  - Stores company-scoped dashboard card values.
- `settings`
  - Stores company-scoped settings by `group` and `key`, with JSON `value`.
- `audit_logs`
  - Stores company, user, event, optional auditable model, description, properties, IP address, user agent, and timestamp.

Phase 1 changes to `users`:

- `company_id`
- `branch_id`
- `role`
- `is_active`
- `last_login_at`

## Roles

Roles are represented by `App\Enums\UserRole`.

- `administrator`
  - Full Phase 1 Command Center access.
  - Can access settings.
- `manager`
  - Can access settings.
  - Intended for operational management access.
- `staff`
  - Can access authenticated Command Center modules.
  - Cannot access settings in Phase 1.

Role checks are handled through `User::hasAnyRole()` and the `role` middleware.

Module visibility and module page access also use the role metadata exposed by `config/modules.php`.

## Module Registry

Phase 1.5 adds a centralized module registry under `app/Support/Modules`.

Files:

- `app/Support/Modules/Module.php`
  - Immutable module value object.
  - Exposes route metadata, role checks, active-state checks, badge metadata, and future child-module support.
- `app/Support/Modules/ModuleRegistry.php`
  - Loads modules from `config/modules.php`.
  - Provides filtered and grouped module collections for controllers and views.
- `app/Support/Modules/ModuleServiceProvider.php`
  - Registers `ModuleRegistry` as a singleton.
  - Added to `bootstrap/providers.php`.
- `config/modules.php`
  - Single source of truth for module metadata.

Module metadata fields:

- `id`
- `name`
- `description`
- `icon`
- `route`
- `route_params`
- `sort_order`
- `category`
- `enabled`
- `visible_in_sidebar`
- `roles`
- `badge`
- `license_key`
- `parent_id`

Registry methods:

- `all()`
  - Returns every configured module, including disabled and hidden modules.
- `enabled()`
  - Returns enabled modules ordered by `sort_order`.
- `sidebar()`
  - Returns enabled, visible modules, optionally filtered by role.
  - Attaches future child modules to their parent modules.
- `find()`
  - Finds a module by id.
- `grouped()`
  - Returns sidebar modules grouped by category.
- `forRole()`
  - Returns enabled modules allowed for a role.

## Module Architecture

The Command Center now treats modules as registered metadata instead of hardcoded sidebar rows.

Current consumers:

- `resources/views/layouts/admin.blade.php`
  - Calls `ModuleRegistry::grouped()` for the authenticated user's role.
  - Renders sidebar category groups dynamically.
  - Renders icons, badges, active states, collapsed menu labels, and future child links.
- `app/Http/Controllers/CommandCenter/ModuleController.php`
  - Calls `ModuleRegistry::find()`.
  - Uses registry metadata for module titles and module access decisions.
- `config/modules.php`
  - Owns all sidebar/module metadata.

No controller should manually add sidebar items. Future modules should register metadata through the module registry configuration or through a future module provider, then expose their own routes and domain logic independently.

## Adding New Modules

To add a future module:

1. Add a new entry to `config/modules.php`.
2. Choose a stable module id, for example `loyalty`.
3. Set the display metadata:
   - `name`
   - `description`
   - `icon`
   - `category`
   - `sort_order`
4. Set route metadata:
   - Use `route => 'modules.show'` and `route_params => ['module' => 'loyalty']` for foundation pages.
   - Use a dedicated route name when the module has its own controller.
5. Set access metadata:
   - `enabled`
   - `visible_in_sidebar`
   - `roles`
6. Add optional metadata:
   - `badge`
   - `license_key`
   - `parent_id` for future nested modules.
7. Add module-specific controllers, requests, repositories, services, models, migrations, and tests as needed.
8. Do not edit the admin sidebar manually.

## Enterprise CMS Architecture

Phase 1.6 adds the Enterprise CMS Foundation for RetailPOS.biz and future CrystalPro web properties. The CMS is administrator/manager managed, company-scoped, auditable, and prepared for future API consumption.

CMS module registration:

- Module id: `cms`
- Menu label: `CMS`
- Category: `Content Management`
- Icon: `layout`
- Route: `cms.dashboard`
- Roles: `administrator`, `manager`
- Staff access: blocked by existing role middleware

### CMS Folder Structure

```text
app/
  Http/
    Controllers/
      CommandCenter/
        Cms/
          CmsDashboardController.php
          CmsHomepageController.php
          CmsMediaController.php
          CmsMenuController.php
          CmsPageController.php
          CmsSeoController.php
          CmsSettingsController.php
    Requests/
      Cms/
        StoreCmsMediaFolderRequest.php
        StoreCmsMediaRequest.php
        StoreCmsMenuItemRequest.php
        StoreCmsMenuRequest.php
        StoreCmsPageRequest.php
        StoreCmsRedirectRequest.php
        UpdateCmsFooterRequest.php
        UpdateCmsPageRequest.php
        UpdateCmsSeoRequest.php
        UpdateCmsSettingsRequest.php
        UpdateHomepageSectionRequest.php
  Models/
    Cms/
      CmsBrokenLink.php
      CmsFooterProfile.php
      CmsHomepageSection.php
      CmsHomepageSectionItem.php
      CmsMedia.php
      CmsMediaFolder.php
      CmsMenu.php
      CmsMenuItem.php
      CmsPage.php
      CmsPageRevision.php
      CmsPageSeo.php
      CmsRedirect.php
      CmsSeoSetting.php
      CmsSetting.php
      CmsSocialLink.php
  Repositories/
    Cms/
      CmsHomepageRepository.php
      CmsMediaRepository.php
      CmsMenuRepository.php
      CmsPageRepository.php
      CmsSeoRepository.php
      CmsSettingsRepository.php
  Services/
    Cms/
      CmsHomepageService.php
      CmsMediaService.php
      CmsMenuService.php
      CmsPageService.php
      CmsSeoService.php
      CmsSettingsService.php

config/
  cms.php

resources/
  views/
    command-center/
      cms/
        dashboard.blade.php
        homepage/index.blade.php
        media/index.blade.php
        menus/index.blade.php
        pages/_form.blade.php
        pages/create.blade.php
        pages/edit.blade.php
        pages/index.blade.php
        partials/nav.blade.php
        seo/index.blade.php
        settings/index.blade.php

tests/
  Feature/
    CmsFoundationTest.php
```

### CMS Database Design

Phase 1.6 adds normalized CMS tables:

- `cms_pages`
  - Company-scoped dynamic pages with slug, title, subtitle, hero content, body content, featured image, draft/published/scheduled status, publish timestamp, scheduled timestamp, and soft deletes.
- `cms_page_seo`
  - Page-level meta title, meta description, meta keywords, canonical URL, Open Graph fields, Twitter Card fields, and media references.
- `cms_page_revisions`
  - Revision history for page content and status, tied to the editing user.
- `cms_homepage_sections`
  - Independent homepage sections for Hero, Features, Benefits, Modules, Industries, Solutions, Pricing CTA, Testimonials, Partners, Statistics, FAQ, Final CTA, and Footer CTA.
- `cms_homepage_section_items`
  - Repeatable section items for future feature cards, module cards, testimonials, partner cards, statistics, FAQ entries, and similar content.
- `cms_menus`
  - Header, footer, mega, and legal menus with enable/disable support and soft deletes.
- `cms_menu_items`
  - Nested-menu-ready menu items with parent item, icon, URL, target behavior, enable/disable, and ordering.
- `cms_media_folders`
  - Folder tree foundation for the media library.
- `cms_media`
  - Media library records for images, SVGs, PDFs, videos, and files with folder, uploader, disk/path, MIME type, extension, file type, size, alt text, optimization flag, and soft deletes.
- `cms_footer_profiles`
  - Footer company information, contact fields, business hours, map URL, and copyright.
- `cms_settings`
  - Website name, tagline, default meta, logos, favicon, contact, business hours, address, and map settings without JSON blobs.
- `cms_social_links`
  - Social link records with platform, URL, icon, enable/disable, and ordering.
- `cms_seo_settings`
  - Global SEO defaults, robots.txt, Schema.org markup, sitemap flag, Search Console verification, Google Analytics, Google Tag Manager, Facebook Pixel, LinkedIn Insight, and Microsoft Clarity.
- `cms_redirects`
  - Redirect manager foundation with source URL, target URL, status code, enable/disable, hit count, and soft deletes.
- `cms_broken_links`
  - Broken-link monitor foundation for future crawler checks.

### CMS Admin Routes

All CMS admin routes are protected by:

- `auth`
- `role:administrator,manager`

Route group:

- Prefix: `/cms`
- Name prefix: `cms.`

Routes:

- `GET /cms` named `cms.dashboard`
- `GET /cms/pages` named `cms.pages.index`
- `GET /cms/pages/create` named `cms.pages.create`
- `POST /cms/pages` named `cms.pages.store`
- `POST /cms/pages/bulk` named `cms.pages.bulk`
- `GET /cms/pages/{page}/edit` named `cms.pages.edit`
- `PUT /cms/pages/{page}` named `cms.pages.update`
- `DELETE /cms/pages/{page}` named `cms.pages.destroy`
- `POST /cms/pages/{page}/restore` named `cms.pages.restore`
- `POST /cms/pages/{page}/publish` named `cms.pages.publish`
- `POST /cms/pages/{page}/unpublish` named `cms.pages.unpublish`
- `GET /cms/homepage` named `cms.homepage.index`
- `PUT /cms/homepage/{section}` named `cms.homepage.update`
- `GET /cms/menus` named `cms.menus.index`
- `POST /cms/menus` named `cms.menus.store`
- `PUT /cms/menus/{menu}` named `cms.menus.update`
- `DELETE /cms/menus/{menu}` named `cms.menus.destroy`
- `POST /cms/menus/{menu}/restore` named `cms.menus.restore`
- `POST /cms/menus/{menu}/items` named `cms.menus.items.store`
- `GET /cms/media` named `cms.media.index`
- `POST /cms/media` named `cms.media.store`
- `POST /cms/media/folders` named `cms.media.folders.store`
- `DELETE /cms/media/{media}` named `cms.media.destroy`
- `GET /cms/settings` named `cms.settings.index`
- `PUT /cms/settings` named `cms.settings.update`
- `PUT /cms/settings/footer` named `cms.settings.footer.update`
- `GET /cms/seo` named `cms.seo.index`
- `PUT /cms/seo` named `cms.seo.update`
- `POST /cms/seo/redirects` named `cms.seo.redirects.store`

### CMS Services and Repositories

CMS repositories:

- `CmsPageRepository`
- `CmsHomepageRepository`
- `CmsMenuRepository`
- `CmsMediaRepository`
- `CmsSeoRepository`
- `CmsSettingsRepository`

CMS services:

- `CmsPageService`
  - Creates pages, updates pages, writes SEO records, creates revisions, publishes, unpublishes, soft deletes, restores, and logs explicit page actions.
- `CmsHomepageService`
  - Ensures default homepage sections and updates each section independently.
- `CmsMenuService`
  - Creates menus, updates menus, adds menu items, and restores menus.
- `CmsMediaService`
  - Creates folders, uploads files, classifies media type, and records upload audit activity.
- `CmsSeoService`
  - Updates SEO settings and creates redirects.
- `CmsSettingsService`
  - Ensures default CMS settings, updates global settings, updates footer data, and replaces social links.

### CMS Audit Coverage

CMS models use the existing `Auditable` concern where CRUD activity should be recorded.

Explicit CMS audit events include:

- `cms.page.published`
- `cms.page.unpublished`
- `cms.page.restored`
- `cms.homepage.updated`
- `cms.menu.restored`
- `cms.media.uploaded`
- `cms.seo.updated`
- `cms.settings.updated`
- `cms.footer.updated`
- `cms.social_links.updated`

### CMS Future Extensions

The CMS foundation is prepared for:

- Blogs
- News
- Knowledge Base
- Documentation
- Landing Pages
- Case Studies
- Testimonials
- Career Pages
- FAQ
- Dynamic Forms
- Public website APIs
- Sitemap generation jobs
- Broken-link crawler jobs
- Media optimization queues
- Search Console reporting
- Public-site cache invalidation

## Settings

Settings are defined centrally in `config/command-center.php` under `settings_sections`.

Sections:

- `general`
  - `timezone`
  - `currency`
  - `date_format`
- `company`
  - `company_name`
  - `tax_id`
  - `registered_address`
- `business`
  - `fiscal_year_start`
  - `default_branch`
  - `stock_alert_threshold`
- `email`
  - `from_name`
  - `from_email`
  - `support_email`
- `notifications`
  - `low_stock_alerts`
  - `daily_sales_digest`
  - `lead_alerts`
- `theme`
  - `mode`
  - `accent_color`
  - `compact_sidebar`
- `security`
  - `session_timeout`
  - `require_mfa`
  - `audit_retention_days`

Settings persistence:

- `SettingsRepository::sections()` reads settings definitions from config.
- `SettingsRepository::valuesFor()` loads saved values for the authenticated user's company.
- `SettingsRepository::updateSection()` writes settings to the `settings` table.
- `UpdateSettingsRequest` validates fields based on the configured field type.

## Audit Log

Audit logging is handled by `App\Services\AuditLogger`.

Recorded in Phase 1:

- `auth.login`
  - Recorded through a Laravel `Login` event listener in `AppServiceProvider`.
  - Also updates `last_login_at`.
- `auth.logout`
  - Recorded through a Laravel `Logout` event listener in `AppServiceProvider`.
- `created`
  - Recorded by the `Auditable` model concern.
- `updated`
  - Recorded by the `Auditable` model concern.
- `deleted`
  - Recorded by the `Auditable` model concern.
- `settings.updated`
  - Recorded by `SettingsController` after a settings section is saved.

Auditable models in Phase 1:

- `Company`
- `Branch`
- `Setting`

Audit-log fields:

- `company_id`
- `user_id`
- `event`
- `auditable_type`
- `auditable_id`
- `description`
- `properties`
- `ip_address`
- `user_agent`
- `created_at`

## Dashboard Widgets

Dashboard metrics are stored in `dashboard_statistics` and loaded with `DashboardRepository`.

Seeded widgets:

- Total Sales
- Orders Today
- Customers
- Products
- Low Stock
- Leads
- Branches
- Employees

Each widget supports:

- `key`
- `label`
- `value`
- `trend`
- `tone`
- `sort_order`

The dashboard view also displays recent audit activity for the authenticated user's company.

## Authentication Flow

Login:

1. Guest visits `/login`.
2. `AuthenticatedSessionController@create` renders the login view.
3. User submits email, password, and optional remember-me flag.
4. `LoginRequest` validates the payload.
5. `LoginRequest::authenticate()` attempts authentication with:
   - `email`
   - `password`
   - `is_active = true`
6. Laravel regenerates the session.
7. User is redirected to `/dashboard`.
8. Laravel's `Login` event records `auth.login` and updates `last_login_at`.

Logout:

1. Authenticated user submits the logout form from the user dropdown.
2. `AuthenticatedSessionController@destroy` logs out the web guard.
3. The session is invalidated.
4. The CSRF token is regenerated.
5. User is redirected to `/login`.
6. Laravel's `Logout` event records `auth.logout`.

Forgot password:

1. Guest visits `/forgot-password`.
2. User submits an email address.
3. `PasswordResetLinkController@store` calls Laravel's password broker.
4. The configured mail transport handles reset-link delivery.

Reset password:

1. User opens `/reset-password/{token}` from the reset link.
2. User submits email, password, password confirmation, and token.
3. `NewPasswordController@store` validates and resets the password through Laravel's password broker.
4. User is redirected to login after a successful reset.

## Current Limitations

- Phase 1 uses seeded demo statistics only. Dashboard values are not yet calculated from sales, inventory, CRM, or order tables.
- Sidebar modules such as POS, Inventory, Orders, Finance, Marketing, SEO, WhatsApp, Analytics, Reports, AI Assistant, Users, Branches, and Company currently route to foundation module screens until their dedicated phases are built.
- Settings are persisted, but most settings are not yet applied to runtime behavior.
- Roles are basic enum-backed roles. Granular permissions are not implemented yet.
- Multi-company support has the tenant data model foundation, but request-level tenant resolution and subscription/billing boundaries are not implemented yet.
- Audit logging covers login, logout, settings updates, and CRUD-style model events for auditable foundation models. Full domain auditing will be expanded as Phase 2+ modules are built.
- Password reset depends on the configured Laravel mail transport.
- The repository currently has a framework constraint of `laravel/framework` `^13.8` in `composer.json`, even though the requested product target references Laravel 12.

## Architecture Decisions

- Kept Phase 1 incremental and additive.
  - Existing Laravel skeleton files were extended only where needed.
  - New domain behavior was added through dedicated models, controllers, requests, repositories, services, and config.
- Used first-party Laravel authentication primitives.
  - No starter kit or unnecessary package was added.
  - Login, logout, remember-me, forgot password, and reset password are implemented directly with Laravel guards and password broker APIs.
- Centralized module metadata in `config/modules.php`.
  - The sidebar and module controller read from `ModuleRegistry` instead of duplicating labels and route metadata.
- Kept settings field definitions in `config/command-center.php`.
  - The settings UI reads section and field definitions from config instead of duplicating labels and keys across views.
- Used enum-backed roles.
  - `UserRole` gives type-safe role values while keeping the database simple.
- Added repository classes where they reduce controller responsibility.
  - `DashboardRepository` owns dashboard metric retrieval.
  - `SettingsRepository` owns settings definition/value lookup and persistence.
- Added a small audit service instead of a package.
  - `AuditLogger` keeps the foundation transparent and easy to extend.
- Prepared for SaaS without forcing full tenancy yet.
  - `companies`, `branches`, and company-scoped users/settings/statistics establish the relationship model for future tenant isolation.
- Kept UI server-rendered with Blade and Tailwind.
  - The Command Center shell is responsive, mobile-friendly, dark-mode-ready, and does not require a JavaScript framework.
- Kept tests focused on Phase 1 behavior.
  - Feature tests cover protected dashboard access, login, role-protected settings, settings persistence, and audit-log creation.

## Enterprise CRM Architecture

Phase 2 adds the Enterprise CRM Foundation. CRM is company-scoped, role-aware, auditable, server-rendered, and intentionally limited to relationship and pipeline workflows. Phase 2 does not build POS, Inventory, Finance, AI, Quotes, Orders, Customers, Invoices, or external messaging integrations.

CRM module registration:

- Parent module id: `crm`
- Category: `Sales & CRM`
- Route: `crm.dashboard`
- Roles: `administrator`, `manager`, `sales`
- Child modules: `crm-dashboard`, `leads`, `crm-companies`, `contacts`, `crm-pipeline`, `crm-activities`, `crm-follow-ups`

### CRM Folder Structure

```text
app/
  Enums/
    Crm/
      ActivityType.php
      LeadPriority.php
      LeadStageType.php
      PreferredContactMethod.php
  Http/
    Controllers/
      CommandCenter/
        Crm/
          ActivityController.php
          ContactController.php
          CrmCompanyController.php
          CrmDashboardController.php
          FollowUpController.php
          LeadController.php
          PipelineController.php
    Requests/
      Crm/
        BulkLeadActionRequest.php
        CompleteActivityRequest.php
        ConvertLeadRequest.php
        RescheduleActivityRequest.php
        StoreActivityRequest.php
        StoreCrmCompanyRequest.php
        StoreCrmContactRequest.php
        StoreLeadRequest.php
        StoreNoteRequest.php
        TransitionLeadStatusRequest.php
        UpdateCrmCompanyRequest.php
        UpdateCrmContactRequest.php
        UpdateLeadRequest.php
  Models/
    Crm/
      CrmActivity.php
      CrmCompany.php
      CrmContact.php
      CrmLead.php
      CrmLeadSource.php
      CrmLeadStatus.php
      CrmNote.php
      CrmTag.php
  Policies/
    Crm/
      CrmActivityPolicy.php
      CrmCompanyPolicy.php
      CrmContactPolicy.php
      CrmLeadPolicy.php
  Repositories/
    Crm/
      ActivityRepository.php
      ContactRepository.php
      CrmCompanyRepository.php
      LeadRepository.php
      PipelineRepository.php
  Services/
    Crm/
      ActivityService.php
      LeadConversionService.php
      LeadService.php
      PipelineService.php

config/
  permissions.php

resources/
  views/
    command-center/
      crm/
        activities/index.blade.php
        companies/_form.blade.php
        companies/create.blade.php
        companies/edit.blade.php
        companies/index.blade.php
        companies/show.blade.php
        contacts/_form.blade.php
        contacts/create.blade.php
        contacts/edit.blade.php
        contacts/index.blade.php
        contacts/show.blade.php
        dashboard.blade.php
        followups/index.blade.php
        leads/_form.blade.php
        leads/create.blade.php
        leads/edit.blade.php
        leads/index.blade.php
        leads/show.blade.php
        partials/nav.blade.php
        pipeline/index.blade.php
```

### CRM Database Design

Phase 2 adds normalized CRM tables:

- `crm_lead_sources`
  - Company-scoped source definitions for inbound leads.
- `crm_lead_statuses`
  - Company-scoped pipeline/status definitions with stage type, probability, won/lost flags, active flag, and ordering.
- `crm_companies`
  - CRM account records linked to the tenant company, optional branch, and assigned owner.
- `crm_contacts`
  - CRM contact records linked to tenant company, optional CRM company, optional branch, and assigned owner.
- `crm_leads`
  - Lead records linked to tenant company, optional CRM company/contact, source, status, owner, creator, value, priority, follow-up timestamps, and conversion timestamp.
- `crm_activities`
  - Calls, meetings, email tasks, WhatsApp tasks, follow-ups, notes, scheduling, completion, outcome, and owner fields.
- `crm_notes`
  - Polymorphic notes for leads, companies, and contacts.
- `crm_tags`
  - Company-scoped CRM tags.
- `crm_lead_tag`
  - Lead/tag pivot.
- `crm_company_tag`
  - CRM company/tag pivot.
- `crm_contact_tag`
  - Contact/tag pivot.

### CRM Models and Relationships

- `Company`
  - Owns CRM companies, contacts, leads, and activities.
- `User`
  - Owns assigned CRM leads, companies, contacts, activities, created leads, and created activities.
- `CrmCompany`
  - Belongs to the tenant company, branch, and assigned user.
  - Has contacts, leads, activities, polymorphic notes, and tags.
- `CrmContact`
  - Belongs to the tenant company, optional CRM company, branch, and assigned user.
  - Has leads, activities, polymorphic notes, and tags.
- `CrmLead`
  - Belongs to tenant company, optional CRM company, optional contact, source, status, assigned user, creator, and branch.
  - Has activities, polymorphic notes, and tags.
- `CrmActivity`
  - Belongs to tenant company, optional lead, optional CRM company, optional contact, assigned user, and creator.

### CRM Routes

CRM routes are defined in `routes/web.php` under:

- Prefix: `/crm`
- Route name prefix: `crm.`
- Middleware: `auth`, `role:administrator,manager,sales`, `can:crm.view`

Route groups include:

- `GET /crm` named `crm.dashboard`
- Lead CRUD, restore, bulk status/assignment, notes, and conversion under `crm.leads.*`
- Company CRUD and restore under `crm.companies.*`
- Contact CRUD and restore under `crm.contacts.*`
- Pipeline board and transition under `crm.pipeline.*`
- Activity scheduling, completion, and rescheduling under `crm.activities.*`
- Follow-up queue under `crm.followups.index`

### CRM Permissions

Phase 2 adds a first-party permission registry in `config/permissions.php` and registers capability gates in `AppServiceProvider`.

Capabilities:

- `crm.view`
- `crm.leads.view`
- `crm.leads.create`
- `crm.leads.update`
- `crm.leads.delete`
- `crm.leads.assign`
- `crm.leads.convert`
- `crm.companies.manage`
- `crm.contacts.manage`
- `crm.activities.manage`
- `crm.pipeline.manage`
- `crm.settings.manage`

Role behavior:

- `administrator`
  - Full CRM access.
- `manager`
  - Full CRM operational access.
- `sales`
  - CRM access for assigned or self-created records.
  - Cannot perform destructive lead delete/restore or lead assignment gates.
- `staff`
  - No CRM access.

Policies:

- `CrmLeadPolicy`
- `CrmCompanyPolicy`
- `CrmContactPolicy`
- `CrmActivityPolicy`

Repositories also enforce tenant and Sales ownership filtering so direct index/show access does not leak records across companies or assignments.

### CRM Services and Repositories

Repositories:

- `LeadRepository`
  - Lead search, filtering, pagination, dashboard metrics, option lists, and Sales ownership scoping.
- `CrmCompanyRepository`
  - Company search, pagination, show lookup, and owner scoping.
- `ContactRepository`
  - Contact search, pagination, show lookup, and owner scoping.
- `PipelineRepository`
  - Server-rendered pipeline grouping by active statuses.
- `ActivityRepository`
  - Activity queues, follow-up queues, upcoming activity lists, and owner scoping.

Services:

- `LeadService`
  - Create, update, soft delete, restore, assign, status changes, bulk status, bulk assignment, tag sync, notes, and audit events.
- `LeadConversionService`
  - Converts qualified leads into CRM companies and contacts inside a database transaction.
  - Reuses existing CRM company/contact records when ids are supplied.
  - Performs a basic duplicate-safe lookup by company/name or contact email/phone.
  - Sets `converted_at`, transitions to a won status when configured, creates a conversion note, and records audit activity.
- `ActivityService`
  - Creates, completes, and reschedules CRM activities with audit events.
- `PipelineService`
  - Validates company-scoped active statuses and records pipeline transition audit events.

### CRM Dashboard Widgets

CRM dashboard widgets are calculated from CRM tables, not hardcoded in Blade:

- Total Leads
- New Leads
- Qualified Leads
- Demo Scheduled
- Won Leads
- Lost Leads
- Pipeline Value
- Overdue Follow-ups
- Leads by Source
- Leads by Status
- Recent Leads
- Upcoming Activities

### CRM Audit Log

CRM uses the existing `Auditable` concern for CRUD-style model events and explicit domain audit events for:

- `crm.lead.created`
- `crm.lead.updated`
- `crm.lead.deleted`
- `crm.lead.restored`
- `crm.lead.assigned`
- `crm.lead.status_changed`
- `crm.lead.bulk_status_changed`
- `crm.lead.bulk_assigned`
- `crm.lead.note_added`
- `crm.lead.converted`
- `crm.pipeline.transitioned`
- `crm.activity.created`
- `crm.activity.completed`
- `crm.activity.rescheduled`
- `crm.company.created`
- `crm.company.updated`
- `crm.company.deleted`
- `crm.company.restored`
- `crm.contact.created`
- `crm.contact.updated`
- `crm.contact.deleted`
- `crm.contact.restored`

### CRM Seed Data

`DatabaseSeeder` now creates:

- Admin user: `admin@retailpos.test`
- Manager user: `manager@retailpos.test`
- Sales user: `sales@retailpos.test`
- CRM lead sources: Website Demo, WhatsApp, Referral, Retail Expo
- CRM lead statuses: New, Contacted, Qualified, Demo Scheduled, Proposal, Won, Lost
- CRM tags: Hot Lead, Multi Branch, Fashion, Grocery, Implementation Ready
- CRM companies and contacts for demo retail accounts
- At least 20 demo leads
- Follow-up activities tied to demo leads

### CRM Lead Lifecycle

1. A user creates a lead with status, source, owner, priority, value, and follow-up metadata.
2. Leads move through configured statuses in the server-rendered pipeline.
3. Activities and notes create a visible lead timeline.
4. Bulk actions allow safe status changes and owner assignment within company scope.
5. Qualified leads can be converted to CRM company/contact records.
6. Conversion preserves the lead, links the new or reused CRM company/contact, sets `converted_at`, and logs the conversion.

### CRM Tenant Isolation

Tenant isolation is enforced through:

- Company-scoped foreign keys on every CRM table.
- Form Request validation that restricts related ids to the authenticated user's company.
- Repository lookup methods that always require the authenticated user.
- Sales ownership filters for assigned/self-created records.
- Route middleware and capability gates.
- Policies prepared for model-level authorization.

### CRM Current Limitations

- CRM uses server-rendered forms and tables only; no drag-and-drop pipeline JavaScript is included in Phase 2.
- CRM does not send email, WhatsApp, SMS, or calendar invites.
- Lead conversion creates CRM company/contact records only; it does not create quotes, orders, customers, invoices, subscriptions, or POS records.
- CRM settings UI is represented by capability foundation only; dedicated CRM settings screens are future work.
- The duplicate prevention logic is intentionally basic and will need stronger matching rules before importing large real-world datasets.
- Reporting is limited to CRM dashboard metrics and lists; advanced forecasting and attribution analytics are future milestones.

## Phase 2.5 - Notification & Event Center Foundation

Phase 2.5 adds the shared event and notification backbone used by CRM, CMS, settings, and future modules. It is company-scoped, user-aware, auditable, queue-ready, and channel-independent.

### Notification Folder Structure

- `app/Contracts/Events/DomainEvent.php`
- `app/Contracts/Notifications/NotificationChannel.php`
- `app/Events/Domain/`
  - `Concerns/SerializesDomainEvent.php`
  - `DomainEventOccurred.php`
  - `Crm/LeadCreated.php`
  - `Crm/LeadAssigned.php`
  - `Crm/LeadStatusChanged.php`
  - `Crm/LeadConverted.php`
  - `Crm/FollowUpDue.php`
  - `Crm/FollowUpOverdue.php`
  - `Cms/CmsPagePublished.php`
  - `Cms/CmsPageUnpublished.php`
  - `Cms/CmsMediaUploaded.php`
  - `System/SettingsUpdated.php`
- `app/Services/Events/DomainEventDispatcher.php`
- `app/Services/Notifications/`
  - `NotificationService.php`
  - `NotificationTemplateRenderer.php`
  - `RecipientResolver.php`
  - `WebhookService.php`
  - `Channels/DatabaseNotificationChannel.php`
  - `Channels/EmailNotificationChannel.php`
  - `Channels/UnsupportedNotificationChannel.php`
- `app/Jobs/Notifications/`
  - `SendNotificationDeliveryJob.php`
  - `SendWebhookDeliveryJob.php`
- `app/Listeners/Notifications/`
  - `DispatchDomainEventNotifications.php`
  - `DispatchDomainEventWebhooks.php`
  - `FinalizeDomainEventLog.php`
- `app/Console/Commands/Notifications/`
  - `DispatchDueFollowUpRemindersCommand.php`
  - `DispatchOverdueFollowUpRemindersCommand.php`
  - `RetryNotificationDeliveriesCommand.php`
  - `PruneDomainEventLogsCommand.php`
- `app/Http/Controllers/CommandCenter/Notifications/`
- `app/Http/Requests/Notifications/`
- `app/Repositories/Notifications/`
- `resources/views/command-center/notifications/`
- `config/events.php`

### Notification Models

- `DomainEventLog`
  - Immutable-ish event ledger for domain events, payloads, correlation IDs, processing status, and retention.
- `NotificationPreference`
  - Per-user event/channel toggles with quiet hours and timezone.
- `NotificationTemplate`
  - CMS-managed templates for database and email notification copy.
- `NotificationDelivery`
  - Delivery attempt log for database, email, and future user channels.
- `WebhookEndpoint`
  - Company-scoped outbound webhook endpoint with encrypted secret and subscribed events.
- `WebhookDelivery`
  - Signed outbound webhook delivery attempts and retry metadata.
- Laravel database notifications table
  - User inbox storage through Laravel's first-party notification system.

### Notification Database Tables

- `notifications`
- `notification_preferences`
- `notification_templates`
- `notification_deliveries`
- `domain_event_logs`
- `webhook_endpoints`
- `webhook_deliveries`

All custom tables include company scoping where relevant. Delivery tables link back to `domain_event_logs` for traceability.

### Event Catalog

`config/events.php` defines the event catalog, default channels, allowed channels, severity, categories, user preference support, webhook eligibility, and future AI flags.

Event families include:

- Authentication: login, logout, password reset requested/completed
- CRM: lead created, updated, assigned, status changed, converted, follow-up due/overdue, activity created/completed
- CMS: page created, updated, published, unpublished, media uploaded, redirects, broken links
- System: user created/deactivated, settings updated, webhook failed, queue failed

### Domain Event Architecture

`DomainEventDispatcher` records the event to `domain_event_logs`, preserves correlation/causation IDs, dispatches the concrete Laravel event, and emits `DomainEventOccurred` for cross-cutting notification and webhook listeners.

Duplicate reminder processing is prevented with deterministic correlation IDs for scheduled follow-up events.

### Notification Channels

Supported in Phase 2.5:

- Database notifications
- Email notifications through Laravel Mail/Notification
- Outbound webhooks

Future-ready but disabled:

- WhatsApp
- SMS
- Push

Unsupported future channels create delivery records with `unsupported` status instead of silently disappearing.

### Notification Center UI

Routes under `/notifications` provide:

- Inbox
  - List, unread/read filter, search, mark read, mark unread, mark all read, delete
- Preferences
  - Per-event database/email toggles, quiet hours, timezone, reset defaults
- Event Log
  - Filterable domain event ledger
- Delivery Log
  - Notification and webhook delivery attempts
- Webhooks
  - Create, update, enable/disable, rotate secret, retry delivery
- Templates
  - Admin-managed notification templates

The admin header dropdown now reads real unread notifications and links to the inbox.

### Notification Permissions

Capabilities added:

- `notifications.view`
- `notifications.manage_own`
- `notifications.preferences.manage_own`
- `notifications.preferences.manage_company`
- `notifications.events.view`
- `notifications.deliveries.view`
- `notifications.templates.manage`
- `notifications.webhooks.view`
- `notifications.webhooks.manage`
- `notifications.webhooks.retry`
- `notifications.settings.manage`

Role behavior:

- Administrator: full Notification Center access
- Manager: operational access to inbox, preferences, event log, delivery log, and webhook view/retry
- Sales: own inbox and own preferences only
- Staff: no Notification Center access

### Module Registry

The Module Registry now includes a parent `notifications` module:

- Name: Notification Center
- Category: System & Operations
- Icon: bell
- Route: `notifications.index`
- Roles: Administrator, Manager, Sales

Child modules:

- Notifications
- Preferences
- Event Log
- Webhooks
- Delivery Log

### CRM Integration

CRM now emits domain events for:

- Lead created
- Lead assigned
- Lead status changed
- Lead converted
- Follow-up due
- Follow-up overdue

Lead creation, assignment, pipeline transition, conversion, and scheduled reminder commands preserve existing CRM behavior and add notification/event side effects.

### CMS and Settings Integration

CMS emits domain events for:

- Page published
- Page unpublished
- Media uploaded

Settings emits:

- Settings updated

### Recipient Resolution

Initial recipient rules:

- Lead created: assigned user plus managers/admins
- Lead assigned: newly assigned user
- Follow-up due/overdue: assigned activity user
- CMS publish/unpublish/media: managers/admins
- Settings updated: admins/managers

### Webhook Foundation

Outbound webhooks support:

- Company-scoped endpoints
- Active/disabled state
- Event subscriptions
- Encrypted secret storage
- Secret rotation
- HMAC SHA-256 signatures
- Event, timestamp, and signature headers
- Queued deliveries
- Retry tracking
- Manual retry
- SSRF guardrails for localhost/private IP targets

Secrets are never displayed in the UI after generation.

### Scheduler

`routes/console.php` schedules:

- `notifications:retry-failed-deliveries` every 15 minutes
- `notifications:dispatch-followup-due` every 15 minutes
- `notifications:dispatch-followup-overdue` hourly
- `notifications:prune-domain-events` daily at 02:30

### Notification Seed Data

`DatabaseSeeder` adds:

- System notification templates
- Per-user CRM notification preferences
- Demo inbox notification
- Demo domain event log
- Demo notification delivery
- Disabled demo webhook endpoint
- Demo webhook delivery record

### Notification Tests

`tests/Feature/NotificationCenterFoundationTest.php` covers:

- Module registry visibility
- Role filtering
- Inbox ownership and read/unread/delete actions
- Preferences update/reset and disabled future channels
- CRM event and database notification emission
- CMS publish and settings event emission
- Unsupported future channel delivery records
- Webhook URL validation, secret rotation, and queued delivery creation
- Scheduled follow-up reminder idempotency
- Seeded foundation records

### Current Limitations

- No external WhatsApp, SMS, Push, RCS, or inbound webhook providers are connected in Phase 2.5.
- Webhook delivery is outbound only.
- No n8n integration is included.
- Reminder commands are foundation-level and use basic due/overdue windows.
- Notification templates support simple `{{ variable }}` interpolation only.
- Delivery logs do not include provider-specific message IDs except where future adapters populate them.
- No POS, Inventory, Finance, Quotes, Invoices, AI automation, or external communication modules are started in Phase 2.5.

## Phase 2.6 - Queue, System Health & Operations Monitor

Phase 2.6 adds a production operations layer for administrators and managers to inspect system health, queues, failed jobs, scheduled tasks, notification deliveries, webhook deliveries, event failures, and safe application metadata.

### Operations Folder Structure

- `app/Console/Commands/Operations/`
  - `RunHealthCheckCommand.php`
  - `CaptureQueueSnapshotCommand.php`
  - `PruneHealthChecksCommand.php`
- `app/Http/Controllers/CommandCenter/Operations/`
  - `OperationsDashboardController.php`
  - `HealthCheckController.php`
  - `QueueMonitorController.php`
  - `FailedJobController.php`
  - `ScheduleMonitorController.php`
  - `ApplicationInfoController.php`
- `app/Models/`
  - `SystemHealthCheck.php`
  - `ScheduledTaskRun.php`
  - `QueueJobSnapshot.php`
- `app/Repositories/Operations/`
  - `HealthCheckRepository.php`
  - `FailedJobRepository.php`
  - `QueueSnapshotRepository.php`
  - `ScheduledTaskRunRepository.php`
- `app/Services/Operations/`
  - `HealthCheckService.php`
  - `QueueMonitorService.php`
  - `ScheduleMonitorService.php`
  - `ApplicationInfoService.php`
  - `FailedJobService.php`
- `resources/views/command-center/operations/`
- `config/operations.php`

### Operations Database Tables

- `system_health_checks`
  - Stores health check snapshots with key, category, status, message, safe payload, and checked timestamp.
- `scheduled_task_runs`
  - Stores command run history, status, timing, output, and failure reason for operational commands.
- `queue_job_snapshots`
  - Stores queue depth snapshots with pending, reserved, failed, and optional processed counts.

Live queue jobs and failed jobs continue to use Laravel's first-party `jobs` and `failed_jobs` tables.

### Operations Module Registry

Parent module:

- `operations`
  - Name: Operations Monitor
  - Category: System & Operations
  - Icon: activity
  - Route: `operations.dashboard`
  - Roles: Administrator, Manager

Child modules:

- System Health
- Queue Monitor
- Failed Jobs
- Schedule Monitor
- Notification Deliveries
- Webhook Deliveries
- Event Logs
- Application Info

Notification delivery, webhook delivery, and event log children link to the existing Phase 2.5 Notification/Event Center screens instead of duplicating them.

### Operations Routes

Route prefix: `/operations`

- `operations.dashboard`
- `operations.health.index`
- `operations.health.run`
- `operations.queue.index`
- `operations.queue.snapshot`
- `operations.failed-jobs.index`
- `operations.failed-jobs.retry`
- `operations.failed-jobs.destroy`
- `operations.failed-jobs.bulk-retry`
- `operations.failed-jobs.bulk-destroy`
- `operations.schedule.index`
- `operations.application.index`

All routes are protected by auth, role middleware, and capability gates.

### Operations Permissions

Capabilities added:

- `operations.view`
- `operations.health.view`
- `operations.queue.view`
- `operations.failed_jobs.view`
- `operations.failed_jobs.retry`
- `operations.failed_jobs.delete`
- `operations.schedule.view`
- `operations.application.view`
- `operations.settings.manage`

Role behavior:

- Administrator: full access, including manual health checks, queue snapshots, retry, delete, bulk retry, and bulk delete.
- Manager: view-only access to dashboard, health, queue, failed jobs, schedule, and app info.
- Sales: no access.
- Staff: no access.

### Health Checks

`HealthCheckService` implements:

- Application boot
- Database connection
- Cache connection
- Queue connection
- Mail configuration
- Storage write/read
- Scheduler availability
- Failed jobs count
- Notification delivery failures
- Webhook delivery failures
- Domain event processing failures
- PHP version
- Laravel version
- Node build manifest status
- Environment configuration sanity

Statuses:

- healthy
- warning
- critical
- unknown

### Queue Monitoring

`QueueMonitorService` reads Laravel queue tables and reports:

- Queue connection
- Queue driver
- Default queue
- Pending jobs
- Reserved jobs
- Failed jobs
- Queue breakdown
- Snapshot history

`operations:capture-queue-snapshot` stores periodic queue snapshots.

### Failed Jobs

Failed jobs use Laravel's `failed_jobs` table.

Supported actions:

- List failed jobs
- Search/filter by queue and connection
- View safe payload summary
- View redacted exception preview
- Retry one failed job
- Delete one failed job
- Bulk retry
- Bulk delete

Retry pushes the stored raw payload back to the original queue connection and removes the failed job record after queuing.

### Scheduler Monitoring

`ScheduleMonitorService` presents configured scheduled commands, expected cadence, estimated next run, last tracked run, status, and failure reason.

Scheduled operations commands:

- `operations:health-check` every 5 minutes
- `operations:capture-queue-snapshot` every 15 minutes
- `operations:prune-health-checks` daily at 03:00

Existing notification scheduled commands are preserved.

### Application Info

`ApplicationInfoService` displays safe runtime metadata:

- App name
- Environment
- Debug mode status
- Laravel version
- PHP version
- Database driver
- Cache driver
- Queue driver
- Mail driver
- Filesystem disk
- Git commit hash
- Current branch
- Last deployment time from Vite manifest when available
- App timezone
- Server timezone

Secrets, API keys, credentials, webhook secrets, and app keys are not rendered.

### Security Redaction

Failed job views never render full raw payloads. `FailedJobService` exposes only:

- UUID
- Display name
- Job handler name
- Retry metadata
- Data key names
- Redacted exception preview

Sensitive terms such as password, token, secret, authorization, API key, credential, and webhook secret are redacted from exception previews.

### Operations Audit Log

Human actions audited:

- `operations.health_check.run`
- `operations.queue_snapshot.captured`
- `operations.failed_job.retried`
- `operations.failed_job.deleted`
- `operations.failed_jobs.bulk_retried`
- `operations.failed_jobs.bulk_deleted`

Scheduled/background command results are stored in `scheduled_task_runs`, not human audit logs.

### Operations Seed Data

`DatabaseSeeder` adds clearly marked demonstration data only:

- Demo application boot health snapshot
- Demo queue connection health snapshot
- Demo queue snapshot for `demo-default`

Live health and queue state are generated by running the operations commands.

### Operations Tests

`tests/Feature/OperationsMonitorFoundationTest.php` covers:

- Module Registry integration
- Role access and Sales/Staff denial
- Dashboard loading
- Notification, webhook, and event failure count surfaces
- Health check command execution
- Database, cache, and storage health checks
- Queue snapshot command
- Schedule list compatibility
- Failed jobs list
- Failed job retry/delete permissions
- Admin retry/delete/bulk actions
- Secret redaction in failed job views
- Operations page loading
- Seeded demo records

### Operations Current Limitations

- No third-party monitoring package is installed.
- No external alerting, Slack, email escalation, or pager integration is connected.
- Queue processed counts are reserved for future worker instrumentation.
- Schedule next-run values are estimated from configured cadence and verified separately by `php artisan schedule:list`.
- Health checks are lightweight application checks, not infrastructure probes for CPU, memory, disk usage, or network latency.
- No Inventory, POS, Finance, Quotes, Invoices, AI, WhatsApp external API, SMS, Push, n8n, or Analytics BI work is included in Phase 2.6.

## Phase 3 - Product & Inventory Foundation

Phase 3 adds the enterprise inventory foundation under the existing Command Center architecture. It preserves Blade + Tailwind, the Module Registry, role middleware, `can:*` capability gates, multi-company scoping, audit logging, and domain-event notification foundations.

### Inventory Folder Structure

```text
app/
  Events/Domain/Inventory/
    ChannelSyncWarning.php
    LowStockDetected.php
    OpeningStockRecorded.php
    OutOfStockDetected.php
    ProductCreated.php
    ProductUpdated.php
    ReorderSuggested.php
    StockAdjusted.php
  Http/
    Controllers/CommandCenter/Inventory/
      BarcodeLabelTemplateController.php
      BarcodePrintBatchController.php
      ChannelProductMappingController.php
      InventoryBrandController.php
      InventoryCategoryController.php
      InventoryDashboardController.php
      InventorySettingsController.php
      InventoryTaxRateController.php
      InventoryUnitController.php
      OpeningStockController.php
      ProductController.php
      ReorderSuggestionController.php
      SalesChannelController.php
      StockAdjustmentController.php
      StockLedgerController.php
      StockLocationController.php
      WarehouseController.php
    Requests/Inventory/
      BarcodeTemplateRequest.php
      OpeningStockRequest.php
      ProductRequest.php
      SalesChannelRequest.php
      StockAdjustmentRequest.php
  Models/Inventory/
    BarcodeLabelTemplate.php
    BarcodePrintBatch.php
    BarcodePrintBatchItem.php
    ChannelProductMapping.php
    ChannelStockLevel.php
    InventoryBrand.php
    InventoryCategory.php
    InventorySyncLog.php
    InventoryTaxRate.php
    InventoryUnit.php
    Product.php
    ProductAttribute.php
    ProductAttributeValue.php
    ReorderRule.php
    ReorderSuggestion.php
    SalesChannel.php
    StockAdjustment.php
    StockAdjustmentItem.php
    StockLevel.php
    StockLocation.php
    StockMovement.php
    Warehouse.php
  Repositories/Inventory/
    ChannelRepository.php
    InventoryLookupRepository.php
    ProductRepository.php
    ReorderRepository.php
    StockRepository.php
  Services/Inventory/
    BarcodeService.php
    ChannelService.php
    InventoryDashboardService.php
    ProductService.php
    ReorderService.php
    StockService.php

resources/views/command-center/inventory/
  dashboard.blade.php
  partials/nav.blade.php
  products/
  catalog/
  warehouses/
  locations/
  stock/
  barcodes/
  reorder/
  channels/
  settings/

tests/Feature/
  InventoryFoundationTest.php
```

### Inventory Database Tables

Phase 3 migration `2026_07_11_040001_create_inventory_foundation_tables.php` creates:

- `inventory_categories`
- `inventory_brands`
- `inventory_units`
- `inventory_tax_rates`
- `products`
- `product_attributes`
- `product_attribute_values`
- `product_variant_attributes`
- `warehouses`
- `stock_locations`
- `stock_levels`
- `stock_movements`
- `stock_adjustments`
- `stock_adjustment_items`
- `barcode_label_templates`
- `barcode_print_batches`
- `barcode_print_batch_items`
- `reorder_rules`
- `reorder_suggestions`
- `sales_channels`
- `channel_product_mappings`
- `channel_stock_levels`
- `inventory_sync_logs`

Tables are company-scoped where needed and prepared for future SaaS isolation. Product, category, brand, warehouse, channel, SKU, slug, and mapping uniqueness is enforced at the database level.

### Inventory Module Registry

`config/modules.php` registers Inventory as a `Retail Operations` parent module routed to `inventory.dashboard`. Child modules include:

- Inventory Dashboard
- Products
- Categories
- Brands
- Units
- Tax Rates
- Variants
- Barcodes
- Barcode Labels
- Warehouses
- Stock Locations
- Stock Ledger
- Stock Adjustments
- Opening Stock
- Low Stock
- Reorder Suggestions
- Sales Channels
- Channel Product Mapping
- Inventory Settings
- Future Purchases and Suppliers placeholders

The sidebar remains fully generated from the Module Registry. `Module::isActive()` now recognizes `inventory.*` routes.

### Inventory Permissions

`config/permissions.php` adds:

- `inventory.view`
- `inventory.products.view`
- `inventory.products.create`
- `inventory.products.update`
- `inventory.products.delete`
- `inventory.products.restore`
- `inventory.categories.manage`
- `inventory.brands.manage`
- `inventory.units.manage`
- `inventory.tax.manage`
- `inventory.warehouses.manage`
- `inventory.stock.view`
- `inventory.stock.opening`
- `inventory.stock.adjust`
- `inventory.stock.approve_adjustment`
- `inventory.barcode.manage`
- `inventory.barcode.print`
- `inventory.reorder.view`
- `inventory.reorder.manage`
- `inventory.channels.view`
- `inventory.channels.manage`
- `inventory.settings.manage`

Administrator and Manager roles have full inventory access. Sales can view products and availability. Staff has no Inventory access.

### Inventory Routes

Inventory routes are grouped under `/inventory` and named `inventory.*`.

Main route groups:

- `/inventory` dashboard
- `/inventory/products`
- `/inventory/categories`
- `/inventory/brands`
- `/inventory/units`
- `/inventory/tax-rates`
- `/inventory/warehouses`
- `/inventory/locations`
- `/inventory/stock-ledger`
- `/inventory/opening-stock`
- `/inventory/adjustments`
- `/inventory/barcode-templates`
- `/inventory/barcode-batches`
- `/inventory/reorder`
- `/inventory/channels`
- `/inventory/channel-mappings`
- `/inventory/settings`

### Product Catalog

Products support:

- List, search, filter, pagination
- Create, edit, view, soft delete, restore
- SKU and barcode
- HSN code
- Category, brand, unit, tax rate
- Cost, selling, MRP, wholesale, online, and purchase prices
- Active/inactive status
- Track inventory flag
- Negative stock flag
- Parent product and variant product foundation
- Attribute/value mapping for variants

### Stock Foundation

`StockService` owns stock mutation rules:

- Opening stock records update `stock_levels`
- Opening stock writes `stock_movements`
- Duplicate opening stock is blocked for the same product, warehouse, and location
- Stock adjustments start as draft
- Approval posts adjustment movements to the ledger
- Approval updates stock levels
- Negative stock is blocked unless the product allows it
- Low-stock and out-of-stock domain events are dispatched from stock thresholds

### Barcode Foundation

Barcode templates support:

- Custom label dimensions in millimeters
- Rows, columns, gaps, and margins
- Barcode type metadata
- Font size
- Show/hide fields
- Default template selection
- Active/inactive state
- CSS-only sample preview without adding a barcode package

Print batches store template, products, label counts, and snapshot label data.

### Reorder Foundation

Reorder rules support:

- Minimum stock
- Maximum stock
- Reorder point
- Reorder quantity
- Safety stock
- Supplier lead-time placeholders
- Preferred supplier placeholder
- Average daily sales
- Seasonal factor
- Approval-ready behavior

Suggestions can be generated, reviewed, and dismissed. Purchase orders are intentionally not created in Phase 3.

### Omnichannel Foundation

Phase 3 creates internal structures only:

- Sales channels
- Channel product mappings
- Channel stock levels
- Inventory sync logs
- Channel sync warning domain events

No external marketplace, website, WhatsApp, POS, or API adapter is connected in Phase 3.

### Audit Log

Inventory actions recorded through `AuditLogger` include:

- Product create, update, delete, restore
- Category, brand, unit, and tax CRUD
- Warehouse and stock location CRUD
- Opening stock recorded
- Stock adjustment created and approved
- Barcode template create/update/default
- Barcode print batch created
- Reorder rule and suggestion actions
- Channel and mapping actions
- Inventory settings updates

### Domain Events

Inventory domain events:

- `inventory.product.created`
- `inventory.product.updated`
- `inventory.stock.opening_recorded`
- `inventory.stock.adjusted`
- `inventory.stock.low`
- `inventory.stock.out`
- `inventory.reorder.suggested`
- `inventory.channel.sync_warning`

These are registered in `config/events.php`. Stock warnings, reorder suggestions, and channel warnings resolve to administrator and manager recipients through `RecipientResolver`.

### Seeded Demo Data

`DatabaseSeeder` adds clearly marked demo inventory data:

- Categories
- Brands
- Units
- GST tax rates
- Products
- Variant products
- Variant attributes and values
- Warehouse
- Stock location
- Stock levels
- Opening stock movements
- Barcode label template
- Barcode print batch
- Reorder rule
- Reorder suggestion
- Sales channels
- Channel product mappings
- Channel stock levels
- Inventory sync warning log
- Inventory settings
- Inventory notification templates

### Inventory Tests

`tests/Feature/InventoryFoundationTest.php` covers:

- Module Registry registration and sidebar children
- Role filtering
- Sales view access and write denial
- Staff denial
- Product CRUD, search, soft delete, restore, audit, domain event
- Tenant isolation
- Category, brand, unit, and tax-rate CRUD
- Variant attribute/value mapping
- Opening stock level updates, ledger creation, duplicate prevention
- Stock adjustment draft and approval flow
- Negative stock guard
- Barcode template and print batch foundation
- Reorder rule and suggestion generation
- Sales channel and product mapping foundation

### Current Phase 3 Limitations

- No POS billing, checkout, invoice, cart, or payment workflow is included.
- No full purchasing, purchase order, goods receipt, supplier payable, or finance workflow is included.
- No external marketplace, website, WhatsApp, SMS, RCS, or accounting integration is connected.
- Barcode rendering uses a CSS preview foundation only; production barcode image generation can be added later behind the existing template model.
- Inventory sync logs are internal readiness records only.
- Reorder suggestions do not create purchase orders.
- Supplier records are placeholders for later purchasing phases.

## Phase 4 - Supplier, Purchase & Stock Decision Foundation

Phase 4 replaces the Phase 3 supplier/purchase placeholders with a tenant-scoped purchasing foundation connected to Inventory. It does not include POS billing, finance/accounting, external supplier APIs, WhatsApp/SMS/Push delivery, n8n automations, or BI analytics.

### Module Registry

`config/modules.php` now registers `purchases` as a Retail Operations parent module routed to `purchases.dashboard`.

Purchase child modules:

- Purchase Dashboard
- Supplier Dashboard
- Suppliers
- Supplier Contacts
- Supplier Products
- Supplier Ratings
- Purchase Requests
- Purchase Orders
- Goods Receipts
- Purchase Returns
- Pending Approvals
- Reorder to Purchase
- Purchase Settings

Inventory also adds `inventory-decision-dashboard`, routed to `inventory.decision-dashboard`.

### Folder Structure

Phase 4 files live under:

- `app/Enums/Purchases`
- `app/Events/Domain/Purchases`
- `app/Models/Purchases`
- `app/Repositories/Purchases`
- `app/Services/Purchases`
- `app/Http/Controllers/CommandCenter/Purchases`
- `resources/views/command-center/purchases`
- `resources/views/command-center/inventory/decision`
- `tests/Feature/PurchaseFoundationTest.php`

### Database Tables

Migration `2026_07_12_050001_create_purchase_foundation_tables.php` creates:

- `suppliers`
- `supplier_contacts`
- `supplier_addresses`
- `supplier_products`
- `supplier_score_snapshots`
- `purchase_settings`
- `purchase_requests`
- `purchase_request_items`
- `purchase_orders`
- `purchase_order_items`
- `goods_receipts`
- `goods_receipt_items`
- `purchase_returns`
- `purchase_return_items`
- `purchase_approval_logs`

All primary operational tables are company scoped. Branch, warehouse, supplier, user, product, stock location, and purchase document relationships are normalized. JSON blobs are avoided.

### Models

Purchase models:

- `Supplier`
- `SupplierContact`
- `SupplierAddress`
- `SupplierProduct`
- `SupplierScoreSnapshot`
- `PurchaseSettings`
- `PurchaseRequest`
- `PurchaseRequestItem`
- `PurchaseOrder`
- `PurchaseOrderItem`
- `GoodsReceipt`
- `GoodsReceiptItem`
- `PurchaseReturn`
- `PurchaseReturnItem`
- `PurchaseApprovalLog`

`Company` and `Branch` now expose purchase relationships for future SaaS and branch-level workflows.

### Enums

Purchase enums:

- `SupplierType`
- `PurchaseRequestStatus`
- `PurchaseRequestPriority`
- `PurchaseOrderStatus`
- `GoodsReceiptStatus`
- `PurchaseReturnStatus`
- `PurchaseSourceType`

### Services

Purchase services:

- `PurchaseNumberService`: purchase settings and sequential PR/PO/GRN/return numbers
- `SupplierService`: supplier CRUD, contacts, addresses, product mapping, audit events
- `SupplierScoreService`: rule-based supplier scoring from purchase, GRN, return, lead-time, and manual service data
- `PurchaseRequestService`: create, submit, approve, reject, convert, and reorder-to-request flow
- `PurchaseOrderService`: create, submit, approve, send, cancel, conversion from request, receipt status updates
- `GoodsReceiptService`: create GRN and post accepted quantities into Inventory stock
- `PurchaseReturnService`: create, approve, complete, and post supplier return stock reductions
- `PurchaseDashboardService`: purchase cards, approvals, recent orders, supplier value
- `SupplierDashboardService`: supplier cards, scores, recent receipts and returns
- `InventoryDecisionService`: product stock decision rows and expected sales days

`StockService` was extended with:

- `recordPurchaseReceipt()`
- `recordPurchaseReturn()`

These methods write `stock_movements` with `movement_type` values `purchase` and `purchase_return`, update `stock_levels`, and preserve the negative-stock guard.

### Controllers And Routes

Purchase controllers:

- `PurchaseDashboardController`
- `SupplierDashboardController`
- `SupplierController`
- `PurchaseRequestController`
- `PurchaseOrderController`
- `GoodsReceiptController`
- `PurchaseReturnController`
- `PurchaseSettingsController`

Inventory controller:

- `InventoryDecisionDashboardController`

Main route groups:

- `/purchases`
- `/purchases/supplier-dashboard`
- `/purchases/suppliers`
- `/purchases/requests`
- `/purchases/orders`
- `/purchases/grn`
- `/purchases/returns`
- `/purchases/settings`
- `/inventory/decision-dashboard`

All purchase routes use `auth`, role middleware, and Gate capabilities.

### Permissions

`config/permissions.php` adds:

- `purchases.view`
- `purchases.dashboard.view`
- `purchases.supplier_dashboard.view`
- `purchases.suppliers.view`
- `purchases.suppliers.create`
- `purchases.suppliers.update`
- `purchases.suppliers.delete`
- `purchases.suppliers.restore`
- `purchases.supplier_products.manage`
- `purchases.supplier_scores.view`
- `purchases.supplier_scores.manage`
- `purchases.requests.view`
- `purchases.requests.create`
- `purchases.requests.update`
- `purchases.requests.approve`
- `purchases.requests.reject`
- `purchases.requests.convert`
- `purchases.orders.view`
- `purchases.orders.create`
- `purchases.orders.update`
- `purchases.orders.approve`
- `purchases.orders.send`
- `purchases.orders.cancel`
- `purchases.grn.view`
- `purchases.grn.create`
- `purchases.grn.receive`
- `purchases.returns.view`
- `purchases.returns.create`
- `purchases.returns.approve`
- `purchases.returns.complete`
- `purchases.settings.manage`
- `inventory.decision_dashboard.view`

Administrator and Manager roles have purchase access. Sales and Staff have no purchase access.

### Supplier Foundation

Supplier records support:

- CRUD with soft delete and restore
- Type, tax IDs, GSTIN, PAN, contact details, payment terms, credit limit, lead time, rating notes
- Contacts with primary contact handling
- Addresses with default address handling
- Product mapping with supplier SKU, price, MOQ, tax, lead time, preferred supplier, and active state
- Supplier score snapshots

Supplier scoring is rule-based only. It does not invent POS sales history.

### Purchase Workflow

Purchase requests support manual creation and creation from reorder suggestions. Requests can be submitted, approved, rejected, and converted to purchase orders.

Purchase orders support creation, approval workflow, sent status, cancellation, totals, item snapshots, and a print-friendly view.

Goods receipts can be created from a PO or without a PO when settings allow it. Receiving posts accepted quantities to Inventory stock, updates PO item received and pending quantities, updates supplier product last purchase data, writes audit records, and dispatches `purchase.goods_received`.

Purchase returns support create, approve, and complete. Completion writes `purchase_return` stock movements and blocks negative stock unless the product allows it.

### Inventory Decision Dashboard

The decision dashboard calculates:

- Available stock
- Average daily sales
- Expected sales days = available stock / average daily sales
- Explicit `Not enough sales data` label when sales data is missing or zero
- Reorder and stockout risk labels
- Preferred supplier display
- AI-ready row metadata without external AI calls

### Audit Log

Purchase actions recorded through `AuditLogger` include:

- Supplier create, update, delete, restore
- Supplier contact/address/product mapping
- Supplier score recalculation
- Purchase request create, submit, approve, reject, conversion
- Purchase order create, submit, approve, send, cancel
- Goods receipt create and receive
- Purchase return create, approve, complete
- Purchase settings updates
- Reorder suggestion conversion to purchase request

### Domain Events

Purchase domain events:

- `purchase.supplier.created`
- `purchase.supplier.updated`
- `purchase.supplier.score_updated`
- `purchase.request.created`
- `purchase.request.submitted`
- `purchase.request.approved`
- `purchase.request.rejected`
- `purchase.request.converted_to_po`
- `purchase.reorder_request.created`
- `purchase.order.created`
- `purchase.order.submitted`
- `purchase.order.approved`
- `purchase.order.sent`
- `purchase.order.cancelled`
- `purchase.goods_received`
- `purchase.return.created`
- `purchase.return.approved`
- `purchase.return.completed`

These are registered in `config/events.php`. Purchase approval, receipt, return, reorder, and supplier-score notifications resolve to administrator and manager recipients.

### Seeded Demo Data

`DatabaseSeeder` adds:

- Purchase settings
- Demo suppliers
- Supplier contacts
- Supplier addresses
- Supplier product mappings
- Supplier score snapshots
- Purchase request
- Purchase order and item
- Goods receipt and item
- Purchase return and item
- Purchase approval logs
- Purchase stock movements
- Purchase notification templates

### Tests

`tests/Feature/PurchaseFoundationTest.php` covers:

- Purchase module registry and sidebar children
- Manager-only access
- Supplier CRUD, contacts, addresses, product mapping, scoring
- Purchase request approval and conversion to PO
- Purchase order lifecycle and print view
- GRN stock posting and PO quantity updates
- Purchase return stock reduction and negative-stock guard
- Inventory decision dashboard sales-data labeling
- Purchase settings
- Reorder-to-request conversion
- Seeded demo purchase data

### Current Phase 4 Limitations

- No POS billing, checkout, invoice, or payment workflow is included.
- No finance/accounting payable posting is included.
- No external supplier API, marketplace, WhatsApp, SMS, push, n8n, or BI integration is connected.
- Supplier scoring is transparent and rule-based; product sales contribution is future-ready until real POS sales history exists.
- Purchase approvals are foundational workflow states, not a configurable approval matrix yet.
- The UI supports one-line quick create forms for purchase documents; richer dynamic line-item editing can be added later without changing the service contracts.

## Phase 4.5 - Discount & Promotion Engine Foundation

Phase 4.5 adds a company-scoped rule engine that future POS, ecommerce, WhatsApp-order, mobile, and marketplace adapters can call with a cart payload. It does not create a POS billing or customer loyalty flow.

### Module Registry and Permissions

`config/modules.php` registers `promotions` under Sales & Marketing with the following children:

- Promotion Dashboard
- Campaigns
- Discount Rules
- Buy X Get Y
- Coupons
- Combo Offers
- Product Offers
- Category Offers
- Brand Offers
- Channel Offers
- Branch Offers
- Promotion Simulator
- Promotion Usage
- Promotion Settings

Administrator and Manager have full operational permissions. Sales can view the promotions dashboard, campaigns, rules, and coupons but cannot create, update, activate, pause, approve, simulate, or edit settings. Staff has no promotion access by default.

### Folder Structure

Promotion code is additive and lives in:

- `app/Enums/Promotions`
- `app/Events/Domain/Promotions`
- `app/Http/Controllers/CommandCenter/Promotions`
- `app/Http/Requests/Promotions`
- `app/Models/Promotions`
- `app/Repositories/Promotions`
- `app/Services/Promotions`
- `resources/views/command-center/promotions`
- `tests/Feature/PromotionFoundationTest.php`

### Database Tables

The migration `2026_07_12_060001_create_promotion_foundation_tables.php` creates:

- `promotion_campaigns`
- `promotion_rules`
- `promotion_conditions`
- `promotion_actions`
- `promotion_product_targets`
- `promotion_category_targets`
- `promotion_brand_targets`
- `promotion_variant_targets`
- `promotion_branch_targets`
- `promotion_channel_targets`
- `promotion_coupons`
- `promotion_coupon_redemptions`
- `promotion_rule_usage`
- `promotion_simulations`
- `promotion_settings`

All operational records have a `company_id`. Campaigns, rules, and coupons use soft deletes where an administrator needs recoverable lifecycle management. Coupon codes and campaign/rule slugs are unique within a company, not globally.

### Models and Relationships

`PromotionCampaign` owns promotion rules. `PromotionRule` owns conditions, actions, target rows, coupons, and rule usage. A rule can target products or variants, inventory categories, inventory brands, branches, and Phase 3 sales channels. `PromotionCoupon` owns redemptions. `PromotionSimulation` stores the submitted cart and full calculation result without pretending it is an order.

Company, Branch, Product, InventoryCategory, InventoryBrand, and SalesChannel expose additive promotion target relationships. No existing inventory, purchasing, CMS, CRM, or authentication model behavior was replaced.

### Repositories and Services

Repositories provide tenant-filtered campaign, rule, coupon, and simulation queries. Services keep controllers thin:

- `PromotionCampaignService` manages campaign lifecycle and domain events.
- `PromotionRuleService` manages rules, actions, targets, lifecycle transitions, approval checks, audit logging, and domain events.
- `PromotionCouponService` manages coupon creation, validation, activation, and future redemption recording.
- `PromotionEligibilityService` evaluates date, bill amount, quantity, branch, channel, target, and configured condition eligibility.
- `PromotionCalculatorService` calculates percentage, flat amount, Buy X Get Y, free-product, and fixed bundle-price foundations.
- `PromotionRuleEngine` returns a stable cart calculation contract.
- `PromotionSimulationService` persists simulator runs.
- `PromotionUsageService` is the future order/POS usage recorder.
- `PromotionSettingsService` supplies company defaults.

### Rule Engine Contract

`PromotionRuleEngine::evaluate($companyId, $cart)` accepts `branch_id`, optional `sales_channel_id`, optional future `customer_id`, optional `cart_reference`, items with product ID, quantity, unit price, optional category/brand/variant data, optional coupon code, and bill subtotal.

It returns:

- `eligible_promotions`
- `applied_promotions`
- `rejected_promotions` with reasons
- `item_discounts`
- `bill_discounts`
- `free_items`
- `total_before_discount`
- `total_discount`
- `total_after_discount`
- `warnings`

The engine applies active rules in priority order. It honors exclusive and non-stackable rules, company-level stacking settings, company maximum bill discount percentage/amount, per-rule/action discount caps, coupon dates/limits, and the coupon-plus-auto-discount setting. Final payable totals are never negative.

Buy X Get Y supports Buy 1 Get 1, Buy 1 Get 2, Buy 2 Get 1, and Buy 2 Get 3 through `buy_quantity` and `get_quantity`. Quantity discounts use `minimum_quantity`; bill discounts use `minimum_bill_amount`. Selected free products are returned as `free_items`, ready for future checkout line creation.

### Admin Routes and UI

All routes are protected by `auth`, role middleware, and first-party capability Gates:

- `/promotions`
- `/promotions/campaigns`
- `/promotions/rules`
- `/promotions/coupons`
- `/promotions/simulator`
- `/promotions/usage`
- `/promotions/settings`

The Blade/Tailwind Command Center UI provides responsive dashboards, search/filter/pagination lists, form-based campaign/rule/coupon administration, target selection, activation/pause/approval controls, usage history, and a cart simulator that saves an auditable simulation result.

### Events, Notifications, and Audit

Promotion events registered in `config/events.php` are:

- `promotion.campaign.created`
- `promotion.campaign.updated`
- `promotion.rule.created`
- `promotion.rule.updated`
- `promotion.rule.activated`
- `promotion.rule.paused`
- `promotion.rule.expired`
- `promotion.coupon.created`
- `promotion.coupon.used`
- `promotion.simulation.ran`
- `promotion.approval.required`

They use the existing `DomainEventDispatcher`, notification resolver, renderer, event logs, webhook foundations, and Operations Monitor failed-job visibility. Important human changes are also recorded with `AuditLogger`.

### Demo Data and Tests

`DatabaseSeeder` idempotently adds a demo festival campaign, settings, Buy 1 Get 1, Buy 1 Get 2, Buy 2 Get 1, Buy 2 Get 3, Buy 3 Get 20% Off, bill-above-1000 10% Off, category, brand, website-channel, branch, and `FESTIVE10` coupon examples. All are explicitly described as demo data.

`PromotionFoundationTest` covers module registration, role filtering, staff denial, campaign CRUD/restore, rule lifecycle, simulator storage, Buy X Get Y calculations, quantity and bill calculations, target eligibility, coupon validation/limits, stacking settings, discount caps, tenant isolation, validation, events, and seeded data.

### Current Phase 4.5 Limitations and Future POS Roadmap

- POS billing, checkout, invoices, payments, and customer-facing receipt presentation are not built.
- Customer loyalty, customer groups, per-customer limits, and birthday offers are schema-ready only; no customer module claim is made.
- The simulator uses a cart payload and stores history, but does not redeem coupons or write rule usage because there is no real order yet.
- External marketplace, WhatsApp, SMS, push, AI, n8n, and analytics BI APIs are not connected.
- V1 rule forms provide a clean single-action foundation with one target per target dimension; the normalized action and target tables support richer future builders without changing cart evaluation contracts.
- Future POS and order services should call `PromotionRuleEngine`, then record selected rules with `PromotionUsageService` and coupon redemption with `PromotionCouponService` only after a successful order transaction.

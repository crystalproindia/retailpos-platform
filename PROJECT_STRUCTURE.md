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

## Phase 4.6 - Website Builder CMS Pro, Content Library & Admin UX Upgrade

Phase 4.6 extends the Phase 1.6 CMS without coupling it to the public website. It provides a company-scoped Website Control Center, brand and theme management, reusable website content records, and an additive design system for the Laravel Command Center. It does not create public-site routes, a Next.js synchronisation layer, a blog, a POS flow, or external integrations.

### Module Registry, Roles, and Permissions

`config/modules.php` retains `cms` as the Content Management parent and marks it as `Pro`. It now exposes registry children for Website Control Center, Branding, Theme, Website Pages, Content Library, and SEO Center. No controller builds sidebar entries manually; the existing Module Registry remains the source of navigation metadata.

Administrator and Manager can access CMS Pro. Staff is denied through the existing role middleware and `cms.view` capability. CMS capabilities are registered in `config/permissions.php`:

- `cms.view` and `cms.website_builder.view`
- `cms.branding.manage`, `cms.theme.manage`, `cms.header.manage`, `cms.footer.manage`
- `cms.pages.manage`, `cms.homepage.manage`, `cms.media.manage`, `cms.seo.manage`, `cms.redirects.manage`
- `cms.client_logos.manage`, `cms.case_studies.manage`, `cms.testimonials.manage`, `cms.trust_metrics.manage`, `cms.faq.manage`, `cms.cta.manage`, and `cms.settings.manage`

### Folder Structure

CMS Pro code is additive:

- `app/Events/Domain/Cms/CmsProDomainEvent.php`
- `app/Http/Controllers/CommandCenter/Cms` for the Website Control Center, branding, theme, header, footer, and content library controllers
- `app/Http/Requests/Cms` for validated CMS Pro form requests
- `app/Models/Cms` for CMS Pro tenant-scoped records
- `app/Repositories/Cms` for tenant-filtered query boundaries
- `app/Services/Cms` for lifecycle, audit, and event orchestration
- `resources/views/command-center/cms` for responsive Blade/Tailwind administration screens
- `resources/views/components` for reusable form sections, status badges, and sticky form actions
- `tests/Feature/CmsProFoundationTest.php`

### Database Design

Migration `2026_07_12_070001_create_cms_pro_content_library_tables.php` extends existing CMS tables with page type/CTA/activity/order metadata, homepage visual metadata, menu badge/description metadata, and regional footer contact fields. It creates:

- `cms_client_logos`
- `cms_case_studies`
- `cms_case_study_sections`
- `cms_testimonials`
- `cms_trust_metrics`
- `cms_cta_blocks`
- `cms_theme_settings`
- `cms_faqs`

All new operational tables have `company_id`, use company-scoped repository queries, and carry soft deletes where recovery is an administrator workflow. Media references use the existing `cms_media` table. Structured arrays are limited to case-study metrics, gallery IDs, section settings, and extensible theme settings; primary queryable content remains normalized.

### Website Builder and Content Library

The Website Control Center presents content counts, readiness indicators, SEO warnings, and recently changed pages. Its managers are:

- Branding: brand name, tagline, logo slots, colour tokens, favicon/Open Graph media slots, and default CTA.
- Theme: primary/secondary/accent/background/text/button colours, theme mode, button/card radius, and header/footer/CTA styles.
- Header: logo slot, sticky setting, menu selection, login/demo/WhatsApp CTA foundations.
- Footer: company contacts, business hours, map link, legal text, social/footer/legal menu links, and India, Singapore, Malaysia, and Bahrain contact blocks.
- Homepage: independently editable hero, features, benefits, modules, industries, solutions, pricing CTA, testimonials, partners, statistics, FAQ, final/footer CTAs, trust metrics, client logos, product showcase, AI features, mobile app, screenshots, and case studies sections.
- Pages: standard, product, solution, industry, marketing, and landing-page foundations with page SEO, CTA, draft/scheduled/published lifecycle, revisions, filtering, pagination, bulk actions, soft delete, and restore.
- Content Library: client logos, case studies with ordered sections, testimonials, trust metrics, FAQs, and reusable CTA blocks.
- SEO Center and Media: existing SEO, redirects, robots, sitemap, verification, and media capabilities are retained and surfaced in the upgraded CMS navigation.

### Service and Repository Boundaries

Controllers coordinate requests, tenant identity, redirects, and views only. Repositories own company-scoped loading and pagination. Services own state changes, audit logging, transactions for case study/section writes, and domain-event dispatch. `CmsWebsiteControlService` composes the read model for the dashboard. This keeps the CMS consumable by a future public-site adapter or API without binding public rendering to admin controllers.

### Events, Notifications, and Audit

The existing domain-event pipeline and audit logger are used for CMS Pro. `config/events.php` registers:

- `cms.branding.updated`, `cms.theme.updated`, and `cms.seo.updated`
- `cms.client_logo.created` and `cms.client_logo.updated`
- `cms.case_study.created`, `cms.case_study.published`, and `cms.case_study.unpublished`
- `cms.testimonial.created`, `cms.trust_metric.updated`, and `cms.cta.updated`

CMS services also audit create, update, delete, restore, publish, unpublish, settings, header, footer, SEO, and content-library actions. The Notification Center recognises CMS Pro events and routes management notices to the existing administrator/manager audience.

### Demo Data and Tests

`DatabaseSeeder` idempotently creates clearly labelled demo branding/theme settings, regional footer contacts, client logo placeholders, an illustrative case study and sections, demo testimonials, a CTA, FAQs, product/solution/industry pages, and homepage-ready trust metrics: `500+ Businesses Served`, `15+ Years Experience`, `100+ Successful Software Projects`, and `24/7 Support`.

`CmsProFoundationTest` verifies Manager access, Staff denial, branding/theme/footer updates, domain events, client-logo tenant isolation plus soft delete/restore, case-study publish/unpublish lifecycle, content-library creation, and demo seed content. `CmsFoundationTest` remains the compatibility contract for the original CMS.

### Current Limitations and Future Extensions

- Public-site rendering, Next.js synchronisation, public API resources, and cache invalidation adapters are intentionally deferred.
- Media usage reporting is a foundation only; future public rendering should register a cross-content usage resolver before exposing destructive media cleanup.
- Page builder sections are structured CMS records, not a freeform drag-and-drop canvas. The normalized content contracts leave room for a future block/page-layout service.
- No blog, news, knowledge base, documentation, careers, dynamic forms, external CDNs, image optimisation service, email, WhatsApp, SMS, analytics API, n8n, or AI content integration is included.
- Case studies and testimonials are seeded only as transparently labelled demo content. Production publication requires approved customer copy and media.

## Phase 5 - Customers, Loyalty & Customer Intelligence Foundation

Phase 5 adds a company-scoped customer command center without implementing POS, orders, invoicing, payments, external messaging, AI, or BI integrations. It extends the existing Laravel Command Center, Module Registry, capability Gates, audit log, Domain Event pipeline, Notification Center, and Branch/Company boundaries.

### Module Registry, Roles, and Permissions

`config/modules.php` registers `customers` as a `Sales & CRM` parent module with Customer Dashboard, Customers, Customer Groups, Loyalty Foundation, Birthday Reminders, Customer Insights, Inactive Customers, Lost Customers, Frequent Returns, Customer Wallet, and Customer Settings children. The parent carries a `New` badge and is resolved by the shared Module Registry, so no controller manages navigation metadata.

Administrator and Manager have complete customer access. Sales can view, create, and update customer records plus view dashboard, customer intelligence, groups, and account history. Sales cannot delete or restore customers, change groups, adjust loyalty or wallet balances, or change customer settings. Staff has no Customer Foundation access. Capabilities are declared centrally in `config/permissions.php` under the `customers.*` namespace.

### Folder Structure

Customer code is additive and follows the established repository/service boundary:

- `app/Enums/Customers` for customer type, status, address, contact, loyalty, wallet, and activity values.
- `app/Events/Domain/Customers/CustomerDomainEvent.php` for the existing domain-event dispatcher.
- `app/Http/Controllers/CommandCenter/Customers` for dashboard, profiles, groups, loyalty, wallet, intelligence, and settings.
- `app/Http/Requests/Customers` for validated customer, group, address, contact, adjustment, and settings forms.
- `app/Models/Customers` for the normalized customer records and lifecycle data.
- `app/Repositories/Customers` for tenant-filtered customer, group, and dashboard queries.
- `app/Services/Customers` for customer number allocation, lifecycle orchestration, group membership, loyalty, wallet, birthdays, intelligence calculations, dashboard composition, and event dispatch.
- `resources/views/command-center/customers` for mobile-responsive Blade/Tailwind dashboard, list, profile, groups, intelligence, and settings screens.
- `tests/Feature/CustomerFoundationTest.php` for customer module behavior.

`Company` now exposes additive `customers`, `customerGroups`, and `customerSettings` relationships. Customer and group models also expose membership relationships without changing prior CRM, CMS, inventory, purchasing, promotion, authentication, or dashboard behavior.

### Database Design

Migration `2026_07_13_080001_create_customer_loyalty_foundation_tables.php` creates twelve company-scoped tables:

- `customers`
- `customer_groups`
- `customer_group_members`
- `customer_addresses`
- `customer_contacts`
- `customer_activity_logs`
- `customer_loyalty_accounts`
- `customer_loyalty_transactions`
- `customer_wallet_transactions`
- `customer_return_summaries`
- `customer_insight_snapshots`
- `customer_settings`

Customer, group, address, and contact records use soft deletes where restoration is an operational requirement. Company-scoped unique keys protect customer numbers, customer emails, group slugs, loyalty numbers, account/snapshot/return-summary rows, and settings. Addresses, contacts, activities, membership rows, ledgers, and intelligence records all retain `company_id` to make tenant filtering explicit.

### Customer Operations

The customer directory provides search, status/type/group filters, pagination, soft-delete filtering, and tenant isolation. Profiles provide core identity, contact information, addresses, business contacts, group membership, loyalty account/history, wallet history, intelligence snapshot, return summary, and chronological activity. The dashboard reports total/active/new/VIP customers, loyalty membership, wallet total, birthdays, inactive/lost/frequent-return counts, top ten customer value, return risk, and segment distribution.

`CustomerNumberService` allocates the next number from company settings in a transaction. `CustomerService` owns create, update, delete, restore, customer activity, audit, default-group assignment, loyalty-account creation, and domain events. `CustomerGroupService`, `LoyaltyService`, and `WalletService` own their mutation lifecycles and guard ledger balances against invalid negative states. Customer wallet transactions are foundations only, not payments or store credit settlement.

### Intelligence and Birthday Foundations

`CustomerInsightService` creates a company-scoped snapshot based on currently available foundation data: customer purchase totals/counts, last purchase, return totals/counts, loyalty tier, configured inactivity/lost thresholds, and frequent-return thresholds. It intentionally labels its notes as rule-based foundation data until Phase 6+ POS/order and return sources are connected.

The dashboard and dedicated screens expose upcoming birthdays, inactive customers, lost customers, and frequent-return customers. `BirthdayReminderService` supplies a provider-neutral reminder foundation and records an activity/event when a future delivery adapter prepares a reminder. It does not send email, WhatsApp, SMS, push, or connect external providers.

### Routes and UI

All `/customers` routes are protected by `auth`, role middleware, and capability Gates. The numeric constraint on `{customer}` prevents conflicts with static Customer Foundation paths. The route surface includes:

- dashboard and searchable customer directory
- customer create, profile, update, soft delete, and restore
- address/contact capture and customer-group assignment
- loyalty/wallet adjustments
- group management with soft delete and restore
- birthday, inactive, lost, frequent-return, and insights screens
- intelligence refresh and customer settings

The Blade/Tailwind screens retain the Command Center shell, breadcrumbs, responsive tables, accessible forms, empty states, status messaging, profile workflow actions, and compact mobile layouts.

### Events, Notifications, and Audit

Customer events are registered in `config/events.php` and use the existing `DomainEventDispatcher`, Notification Center, event logs, webhook foundation, and Operations Monitor:

- `customer.created`, `customer.updated`, `customer.deleted`, `customer.restored`
- `customer.group.assigned`, `customer.status.changed`
- `customer.birthday.upcoming`, `customer.birthday.today`
- `customer.inactive.detected`, `customer.lost.detected`, `customer.frequent_returner.detected`
- `customer.loyalty.points_adjusted`, `customer.wallet.adjusted`

Customer create/update/delete/restore, address/contact capture, group lifecycle/assignment, loyalty and wallet adjustments, settings updates, and explicit intelligence refreshes are also written through `AuditLogger`. Notification recipients use the existing customer-event management audience and renderer mappings.

### Demo Data and Tests

`DatabaseSeeder` idempotently creates transparently labelled demo customer groups, settings, seven customer profiles, addresses, contacts, activity records, loyalty accounts/transactions, wallet transactions, return summaries, and intelligence snapshots. The records intentionally include a top customer, birthday today/upcoming examples, inactive/lost customers, a frequent returner, and a wholesale account.

`CustomerFoundationTest` covers module registration, role filtering, staff denial, sales restrictions, customer CRUD/restore, same-email updates, tenant isolation, group assignment, loyalty/wallet ledger safeguards, audit/domain events, intelligence flags, birthday/inactive/lost/returns screens, refresh behavior, and seeded demo records.

### Current Limitations and Future Extension Points

- No POS billing, order ledger, invoice, payment, real return, refund, or accounting logic is introduced. Future POS/order services should call the dedicated customer, loyalty, wallet, and intelligence services transactionally.
- Purchase and return values are foundation/demo fields until an order/return integration writes authoritative totals and source references.
- Loyalty earn/redeem rules, tier promotion, expiry, wallet payment, credit-limit settlement, customer portal, campaign delivery, external messaging, AI scoring, and BI exports remain intentionally deferred.
- Customer groups expose promotion-ready discount percentage and loyalty multiplier fields, but Phase 5 does not alter the Phase 4.5 promotion engine or make checkout claims.
- Birthday records/events are provider-neutral. A future delivery adapter can use the existing notification pipeline without changing customer profile, activity, or settings contracts.

## Phase 6 - POS, Mobile PWA & Customer Product Suggestions Foundation

Phase 6 turns the existing POS registry placeholder into an additive, company/branch-scoped cashier foundation. It integrates Phase 3 inventory, Phase 4.5 promotion evaluation, and Phase 5 customer intelligence without modifying their existing contracts. It provides server-validated completed and held sales, but deliberately does not claim accounting, external payment gateway, refund, or offline transaction synchronisation support.

### POS Architecture and Database Tables

Migration `2026_07_13_100001_create_pos_foundation_tables.php` creates:

- `pos_sales` for held and completed bills, totals, cashier/device context, customer, and branch ownership.
- `pos_sale_items` for immutable product, SKU, barcode, price, quantity, category, discount, and line-total snapshots.
- `pos_payments` for local cash/card/UPI/bank-transfer/other payment declarations and references.
- `customer_product_summaries` for customer/product purchase count, quantity, spend, first, and last purchase facts.
- `pos_product_pair_summaries` for normalized product-pair co-purchase counts used by add-on recommendations.

All POS operational records carry `company_id`; bill numbers are unique per company. Customer and product foreign keys are tenant-filtered before any POS mutation. `Company`, `Branch`, and `Customer` expose additive POS relationships. Completed bills write inventory sale movements, customer purchase totals/activity, product summaries, and product-pair facts in the same transaction.

### Services, Repositories, Controllers, and Routes

POS code is additive:

- `app/Models/Pos` for sale, line, payment, customer-product, and product-pair records.
- `app/Repositories/Pos` for saleable catalog queries and company-scoped held/receipt loading.
- `app/Services/Pos` for bill numbering, checkout/hold lifecycle, customer mobile lookup, quick capture, and rule-based suggestions.
- `app/Http/Controllers/CommandCenter/Pos/PosController.php` and `app/Http/Requests/Pos` for thin request/view/JSON coordination.
- `resources/views/layouts/pos.blade.php` and `resources/views/command-center/pos` for a dedicated cashier shell and receipt.
- `tests/Feature/PosFoundationTest.php` for POS contracts.

The protected `pos.*` route surface includes the POS workspace, JSON catalog lookup, JSON customer lookup, quick customer capture, hold, checkout, held-bill resume, and receipt view. Administrator, Manager, and Sales roles can operate POS through the central `pos.*` capabilities; Staff is denied. The Module Registry points the POS parent and its New Sale/Held Bills children to the real POS workspace.

### Desktop and Mobile Experiences

The POS view automatically presents a full-width, scanner-first desktop cashier workspace at desktop/tablet breakpoints and a separate mobile-app shell below that breakpoint. The desktop layout contains barcode/product search, product grid, customer panel, suggestion rail, quantity cart, discount/coupon controls, payment selection, hold action, and receipt completion.

The mobile shell has touch-safe product cards, app-like pane transitions, sticky cart access, bottom Sell/Customer/Held navigation, cart drawer, payment panel, customer lookup, quick customer capture, held-bill resume, and receipt view. It uses `pos-manifest.webmanifest`, `pos-icon.svg`, and `pos-sw.js`; the worker only caches the PWA shell assets and intentionally does not cache authenticated carts, customers, or transactions. Full offline queueing/synchronisation is deferred.

### Customer Lookup and Rule-Based Suggestions

POS customer lookup resolves a mobile number against the current company. When found, the cashier receives name, group, loyalty points, wallet balance, last purchase date, birthday, and retention note. When not found, a quick Customer Foundation record is created through the existing `CustomerService`, preserving number allocation, loyalty-account creation, activity, audit, and customer-created event behavior.

`CustomerProductSuggestionService` is intentionally rule based, not AI. It returns active and saleable products in these groups:

- Regular products: highest purchase count and quantity for the selected customer.
- Frequent products: highest quantity/purchase frequency.
- Recently and last purchased products: most recent customer-product timestamps.
- Add-ons: product-pair co-purchase facts first, then active products from the customer’s regular-product categories.

Tracked products without branch stock, inactive products, and inactive catalog records are filtered out. Each suggestion is directly addable to the current cart. Completed POS bills update the summary and pair tables so recommendations improve through actual checkout data.

### Inventory, Promotions, Events, Audit, and Demo Data

Checkout retrieves active company products, validates stock at the selected branch, writes a `sale` stock movement, and prevents negative stock unless the existing product setting permits it. The current Promotion Rule Engine is evaluated for each checkout, alongside an optional manual discount. It remains the source of promotion eligibility; POS does not duplicate discount rules.

`pos.sale.held` and `pos.sale.completed` are registered through the existing Domain Event, Notification Center, webhook, and Operations foundations. POS holds and completed sales are audited. Seed data adds a clearly labelled completed demo POS sale, items, cash payment, customer product summaries, and co-purchase pair facts for the existing demo company.

### Current Limitations and Future POS Roadmap

- No accounting journal, GST invoice workflow, refund/return, cash drawer integration, receipt printer adapter, shift close, external card/UPI gateway, wallet settlement, or payment reconciliation is included.
- A POS payment record is an internal cashier declaration, not gateway confirmation or finance posting.
- Full offline sale creation, conflict resolution, background sync, and encrypted device storage are deferred; only the PWA shell/service-worker foundation exists.
- Loyalty earning/redemption, wallet payment/refund, automatic customer-group pricing, tax calculation, real promotion usage/redemption posting, returns, and e-commerce order merging remain future transactional integrations.
- The recommendation engine is transparent rule logic over POS/customer facts. Future AI may add ranking only after it can preserve these tenant, availability, and explainability constraints.

## Phase 6.1 - POS Terminal UI, Kiosk Mode & Billing Speed Polish

Phase 6.1 is a presentation and operational-read-model enhancement over Phase 6. It does not alter POS checkout, stock posting, promotion evaluation, customer history, events, or payment persistence. It adds dedicated terminal, dashboard, mobile, and held-bill routes while retaining `/pos` as the existing responsive cashier workspace.

### Terminal and Kiosk Foundation

`/pos/terminal` uses the dedicated POS shell without the Command Center sidebar. The terminal header exposes the compact brand mark, branch, cashier, active-session indicator, draft/resume state, current-clock foundation, POS dashboard link, safe browser fullscreen toggle, and Command Center exit. Fullscreen is requested only after a cashier action through the browser Fullscreen API; unsupported browsers simply remain in the normal terminal layout.

The terminal uses a viewport-bound grid with independent catalog and cart scroll regions. It is tuned for 1366×768 through 1920×1080 displays: the scanner/search area stays at the top, the cart total/payment actions stay in the right panel, and neither panel creates a horizontal browser scrollbar. The product grid adapts from five to three tiles on common desktop widths and two tiles on mobile.

### Billing-Speed and Product Tile Polish

The scanner input auto-focuses on terminal load and regains focus after an item is added. F2 or `/` focuses scan/search, F4 focuses customer mobile lookup, F8 holds the current bill, F9 submits payment, and Escape returns the mobile view to Products. Barcode/SKU entry accepts keyboard-wedge USB scanners, gives immediate add/not-found/out-of-stock feedback, and preserves the existing JSON catalog API.

Product tiles now include a product image when configured or a clean initial fallback, name, brand/category, SKU, price, stock state, and low-stock treatment. Category chips filter the already-loaded saleable catalog without changing the server-side inventory rules. Suggestions use the same add-to-cart interaction and maintain the existing rule-based availability filters.

### Cart, Payment, Customer, and Held-Bill Surfaces

The cart has large quantity controls, remove actions, item SKU/rate/tax foundation, line totals, a stronger empty state, visible manual discount/coupon controls, and a fixed grand-total/payment area. Promotion discounts remain server-evaluated at checkout, so the terminal labels that state instead of fabricating a client-side promotion amount.

Cash, Card, and UPI remain the operational payment choices supported by Phase 6. Card/UPI reference capture is sent through the existing payment-reference field. Wallet, Credit, and Split controls are clearly labelled interface foundations only; they do not claim settlement, credit sales, gateway, or terminal capability. Paid/change feedback is calculated from the entered tender without changing the server’s payment validation.

Customer lookup now shows a loading state, matched card, quick create pathway, loyalty/wallet/birthday/retention information, and grouped rule-based product suggestions. `/pos/held` provides searchable cards with customer, mobile, item count, total, held time, and resume action, while preserving the existing cashier ownership guard on resume.

### Mobile PWA and Receipt Polish

`/pos/mobile` is a mobile-only installed-app-style route with Products, Customer, Cart, Payment, and More tabs; sticky bill total/action; touch-sized tiles; payment controls; and held-bill access. The manifest now opens this route. `pos-sw.js` remains intentionally online-only for authenticated POS data but caches a branded offline fallback document so a disconnected navigation has a clear, safe outcome rather than attempting offline billing.

Receipts now offer browser-print layout selection for Standard, 80 mm, and 58 mm widths. The receipt includes company/branch, bill, cashier, customer, immutable line details, discount/tax foundations, payment references, paid/change, and a thank-you footer. It does not add a thermal driver, printer discovery, or any direct printing integration.

### Dashboard Read Model and Current Limitations

`PosDashboardService` is a read-only service over existing `pos_sales`, `pos_sale_items`, and `pos_payments`. `/pos/dashboard` renders actual branch-scoped current-day sales, bills, average bill, held bills, cashiers with completed sales, payment totals, recent bills, and top sold products. It does not introduce day closing, accounting, returns, payment reconciliation, or fabricated metrics.

- No direct thermal printer driver or hardware integration is present; receipt printing is browser print only.
- No payment gateway, UPI/card terminal integration, wallet settlement, credit-sale workflow, or finance/accounting posting is introduced.
- No completed sales, carts, customers, or sensitive payment data are stored for offline use; full offline sync remains deferred.
- Kiosk behavior depends on browser fullscreen support and always requires an explicit user action.
- Hardware barcode scanners work through keyboard input; direct scanner SDK/device integration remains deferred.

## Phase 6.1.2 - Premium POS Selling Screen & Checkout Modal Polish

Phase 6.1.2 is UI/UX-only polish over the Phase 6.1 terminal and the existing Phase 6 checkout workflow. It introduces no migrations, accounting, payment gateway, external terminal, printer driver, offline synchronisation, or change to POS sale/payment persistence.

### Premium Selling Screen

The terminal is now arranged as a compact three-zone cashier surface: a fixed category rail, independently scrolling compact product grid, and a persistent bill panel. The command bar carries brand, branch/cashier context, live clock foundation, online placeholder, a scanner-first search field, fullscreen control, dashboard, and exit. The category rail offers All, Popular, Recently Added (current-session), Offers foundation, Low Stock, and company categories with data-derived counts. Popular is derived from completed POS sale-line history through the existing POS read model, rather than a hardcoded list.

Product cards now fit four to six columns on desktop and two columns on mobile. They expose image/initial fallback, SKU, price, stock badge, and touch/click add behavior. Category/product search empty states explain how to recover by checking a category, SKU, barcode, or name. The terminal retains the scanner keyboard-wedge flow, quick feedback, focus restoration, F2 or `/`, F4, F8, F9, and Escape behavior.

### Bill Panel, Customer Sales Strip, and Checkout Modal

The bill panel remains sticky and is the strongest visual surface: compact cart rows, quantity controls, clear remove control, manual discount/coupon inputs, subtotal/discount/tax foundation, a large grand total, Hold, and an orange Checkout action. Customer lookup retains the existing profile and quick-create contract; its rule-based Regular, Frequent, Recent, Last Purchased, and Add-on products render as compact direct-add sales strips. When no history exists, the UI explicitly says the suggestions will improve as purchase history grows.

Checkout is now initiated through an accessible `data-pos-payment-modal` rather than exposing payment fields in the primary selling screen. Desktop uses a centred, blurred-backdrop modal and mobile uses a bottom-sheet layout. The modal supports persisted Cash/Card/UPI payment selection, exact/quick cash amounts, paid/change calculation, manual Card/UPI reference, optional notes, Escape/backdrop close, and duplicate-submit disabling. Cash defaults to the exact total and the client makes underpayment errors visible before the original server-side validation remains authoritative.

QR, Wallet, Credit, Split, Bank Transfer, and Cheque are visibly labelled interface foundations. They do not post unsupported payment methods, settle a wallet, create credit dues, invoke a QR/UPI/card gateway, or add a split-payment data model. Completed checkout continues to redirect to the existing receipt screen, where browser print and new-bill actions already exist.

### Mobile and Responsive Fit

`/pos/mobile` keeps its Products, Customer, Cart, Pay, and More navigation, compact two-column product grid, sticky cart total, and touch-sized checkout action. Opening checkout invokes the same payment surface as an app-style bottom sheet. Terminal grid regions have independent category, catalog, and cart scrolling; product grids have responsive track limits and no intentional horizontal page overflow.

### Current Limitations

- No payment gateway, QR collection, live UPI/card terminal, wallet settlement, credit due, split-payment persistence, bank transfer, or cheque workflow is connected.
- Card and UPI remain a cashier-declared internal payment with an optional manual reference.
- No direct thermal-printer driver is introduced; receipt output remains browser print.
- No offline bill creation, sensitive local cart/customer storage, or synchronisation is started in this phase.

## Phase 6.1.3 - Checkout Payment Modal & POS Payment UX Polish

Phase 6.1.3 refines only the Phase 6.1.2 Checkout modal. The full POS terminal, product search, customer lookup/suggestions, cart, held bills, receipt, database tables, checkout service, and server-side request rules remain intact.

### Checkout Payment Flow

The sticky Checkout action and F9 open the payment modal only when the cart contains products. The modal remains a centred blurred-backdrop cashier surface on desktop and a bottom sheet on mobile. Escape/backdrop close it before submission. Its primary action becomes disabled with a saving label after submission, while existing Laravel validation stays authoritative.

Cash defaults to the exact bill total, supports editable tender, Exact/Round/common amount shortcuts, and shows change in real time. Card and UPI remain internal cashier declarations: their manual reference field is available, their message explicitly states no gateway/card-terminal integration exists, and their submitted amount must match the total. Reference capture stays optional because there is currently no configured company setting that makes it mandatory.

### Split Payments and Foundation Modes

Split payment is now a real UI over the existing `payments[]` request structure for Cash, Card, and UPI. Cashiers can add/remove rows, select a supported method, enter amount/reference, see paid/remaining totals, and submit only when the rows equal the bill total. The checkout service continues to persist each supported row as a normal `pos_payments` record in the existing sale transaction.

Wallet and Credit are customer-aware but non-settling foundations: the controls are disabled without a selected customer and surface the current customer wallet balance when present. QR, Wallet, Credit, Bank Transfer, Cheque, and unsupported mixed settlement remain visibly labelled future integrations; they do not submit unsupported payment types, reduce wallet balance, create credit due, call external providers, or create accounting entries.

### Mobile, Keyboard, and Current Limits

Mobile keeps the same payment modal as a touch-sized bottom sheet with a sticky accept action. Enter on amount/reference safely activates the current acceptance action, F9 opens payment, and Escape closes it before submission. A successful payment continues to use the existing receipt redirect, which provides browser print, receipt view, and new-bill navigation without adding an asynchronous checkout protocol.

- No gateway, UPI API, QR provider, card terminal, wallet settlement, credit-due ledger, bank-transfer, cheque, or accounting integration is connected.
- No offline payment queue, IndexedDB sale storage, or offline sync is added; the existing PWA fallback remains informational only.
- Direct thermal printer integration remains deferred; receipt output remains browser print.

## Phase 6.2 - Offline POS Mode & Auto Sync Foundation

Phase 6.2 adds an additive offline queue around the existing online POS checkout flow. It preserves the premium terminal, Checkout modal, inventory posting, promotions, customer intelligence, and receipts. Online bills still post directly to Laravel; only a browser-detected offline checkout is stored locally as a Pending Sync bill.

### Server Architecture and Idempotency

Migration `2026_07_13_130001_create_pos_offline_sync_foundation_tables.php` adds `pos_offline_sync_batches`, `pos_offline_sync_records`, and `pos_offline_settings`. It also adds nullable `offline_uuid`, `offline_reference`, `synced_from_offline`, `offline_created_at`, and `device_id` fields to `pos_sales`. The company/offline UUID unique index ensures the same queued bill cannot create more than one official sale.

`PosOfflineSyncService` owns safe bootstrap generation and sync processing. A sync batch contains scoped client records; each record is persisted before processing, then either becomes synced/warning with its official `PosSale` reference, failed with a safe error message, or duplicate without creating another sale. On sync, the service invokes the existing `PosCheckoutService`, so item persistence, payment rows, stock movements, customer purchase totals, customer-product summaries, product-pair suggestions, audit, and standard POS events remain authoritative. Offline price differences are preserved as the cashier-sold price and recorded as warnings for review.

### Offline APIs, Permissions, and Monitor

Authenticated `pos.offline.*` routes provide bootstrap, status, sync, monitor, records, and retry. Bootstrap is company/branch/user scoped and returns active product stock snapshots, minimal customer snapshots, and safe offline settings. Sync requires a batch UUID, browser device ID, record UUIDs, safe payment methods, and company-scoped products. Sales may use/sync their own device records; Administrators and Managers can open `/pos/offline`, inspect all records, and retry failures. The monitor shows status, offline reference, device/cashier, official bill reference, and warning/error context.

The new capabilities are `pos.offline.use`, `pos.offline.sync`, `pos.offline.monitor`, `pos.offline.retry`, and `pos.offline.settings`. Offline sync events are registered through the existing domain-event and notification systems: queued/synced bill, sync started/completed/failed, record failed, and warning.

### IndexedDB Queue and Checkout Integration

`resources/js/pos-offline.js` uses IndexedDB for cache snapshots and pending bills. It stores no secrets, authentication tokens, card data, or private HTML. LocalStorage stores only a generated non-secret device UUID. The terminal refreshes bootstrap data while online, shows online/offline and pending-count state, falls back to cached products/customers when offline, supports an offline quick-customer snapshot, and automatically submits queued bills when the browser `online` event fires. A manual sync control is also available.

Offline Checkout remains inside the Phase 6.1.3 payment modal. Cash is allowed by default; Card/UPI manual references are allowed only when the cached offline setting allows them. Wallet, loyalty redemption, credit, gateway verification, QR collection, and unsupported methods remain disabled or foundation-only offline. A queued bill gets an `OFF-{device}-{date}-{sequence}` reference, shows a Pending Sync confirmation, and does not claim an official server bill number until sync succeeds.

### Service Worker, Conflict Safety, and Limitations

The service worker remains limited to the POS shell/assets and branded fallback; authenticated bootstrap snapshots live in IndexedDB and are refreshed through authenticated routes rather than being long-term service-worker-cached. Server sync is still the final authority. Product price changes produce a warning; duplicate UUIDs never duplicate a sale. Existing stock validation remains active, so a later stock conflict stays visible as a failed sync record for manager review rather than silently rewriting inventory.

- Offline UPI/card references are cashier-entered only; no gateway or terminal verification exists.
- Offline stock is a snapshot; it can differ at sync time and is intended for short outages, not long-term disconnected operation.
- Wallet/loyalty redemption, credit dues, returns/exchanges, multi-device conflict reconciliation, and full offline promotion re-evaluation remain deferred or disabled by default.
- No payment gateway, UPI API, card terminal, direct thermal printer, finance/accounting, WhatsApp/SMS, AI, n8n, or BI integration is introduced.

## Phase 6.3 - Live Deployment & Production/Staging Setup

Phase 6.3 is a deployment-readiness and documentation-only milestone for the separate Laravel Command Center at `https://app.retailpos.biz`. It does not change POS, offline POS, CMS, CRM, inventory, purchases, promotions, customers, dashboard behavior, migrations, queues, scheduler definitions, integrations, or the separate `retailpos-web` application at `https://retailpos.biz`.

### Deployment Assets and Environment Contract

`.env.production.example` supplies a safe committed template with no secrets. It fixes the production contract to `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.retailpos.biz`, secure SameSite-Lax sessions, database cache/session/queue drivers, public filesystem media, warning-level logs, and a log-only mailer. The live `.env`, database credentials, generated `APP_KEY`, and any provider secrets remain server-only and ignored by Git.

`DEPLOYMENT.md` is the operational runbook for Hostinger Business/hPanel. It covers SSH/Git deployment, manual-upload fallback, local Vite builds where server Node is unavailable, Composer fallback guidance, MySQL/MariaDB setup, safe migration rules, writable folder expectations, cache commands, hPanel cron jobs, smoke tests, backup/rollback, and troubleshooting.

### Public-Root and Migration Rules

The mandatory deployment boundary is that only `retailpos-platform/public` is exposed by `app.retailpos.biz`. The Laravel root must remain private; `.env`, source directories, `storage`, `vendor`, migrations, and Composer files must never be served by the browser. The preferred hPanel document root is `/home/<hostinger-user>/retailpos-platform/public`; if that cannot be configured, the deployment must keep the application outside `public_html` and arrange for the subdomain to serve only its public directory. Insecure copy or rewrite workarounds are explicitly excluded.

Production uses `php artisan migrate --force` after a database backup. `migrate:fresh`, `db:wipe`, and other destructive reset operations are prohibited. Demo seeding is allowed only on an intentionally new staging database after the current seeders are reviewed; it is not a replacement for live-user provisioning or an excuse to overwrite production data.

### Queue, Scheduler, PWA, and Security Operations

The existing `jobs`, `job_batches`, and `failed_jobs` migration supports the documented `QUEUE_CONNECTION=database` worker. Shared Hostinger hosting may use a one-minute `queue:work --stop-when-empty` cron fallback; a future VPS can run a supervised persistent worker. The existing scheduler remains unchanged and is run by a separate one-minute `schedule:run` hPanel cron job.

The live smoke-test contract includes HTTPS, login, dashboard, desktop POS, mobile POS, receipt printing, manifest/service-worker registration, IndexedDB bootstrap, a short-outage offline queue, online restoration/sync, and the `/pos/offline` manager monitor. This is deliberately a short-outage facility, not long-term offline or payment-verification support.

The runbook requires production debug disabling, HTTPS, secure session cookies, private server environment files, safe storage/cache permissions, cache verification, non-default administrator credentials, no exposed debug/test routes, and pre-deploy database/storage backups. It also records that the initial deployment remains a staging/demo environment until all smoke tests are passed.

### Current Deployment Limitations

- Real customer billing remains blocked until the live deployment checklist and smoke tests pass.
- External payment gateways, live UPI/card terminals, direct thermal-printer drivers, finance/accounting, WhatsApp/SMS, AI, and n8n remain out of scope.
- Hostinger shared hosting may require cron-based queue execution rather than a persistent queue worker.

## Phase 6.4 - Live Hardening, Cron, Backup, and Demo Readiness

Phase 6.4 adds deployment-safe operational tooling and documentation only. It preserves every application feature and does not add payment, accounting, messaging, AI, automation, or public-site functionality.

### Safe Console Commands

`retailpos:admin-password {--email=}` rotates the password for an existing user with the Administrator role. It prompts for both password entries using hidden console input, requires at least twelve characters, hashes through Laravel's `Hash` facade, and reports success without printing or logging the password. It cannot create or delete users.

`retailpos:live-check` is read-only. It provides PASS/WARN/FAIL results for production environment and debug settings, HTTPS app URL, PHP version, database/migrations, writable storage/cache/log paths, public-storage link, configuration/route cache state, POS and offline POS route availability, queue configuration, scheduler command discovery, and Vite manifest availability. FAIL results make the command return a non-zero exit code; WARN results identify conditions to review without writing data or changing configuration.

`tests/Feature/LiveHardeningCommandTest.php` covers password rotation, non-administrator refusal, hidden-password output safety, and the read-only readiness report.

### Hostinger Operations and Demo Documentation

`DEPLOYMENT.md` now records the deployed Hostinger PHP 8.4 CLI path, exact every-minute scheduler and queue-fallback cron commands, backup boundaries, local Vite build/upload steps for a server without Node, cache rebuild commands, log diagnostics, private-root/.htaccess checks, and the complete live smoke-test flow.

`DEMO_READINESS.md` provides a client-facing readiness checklist for credentials, branding, demo data, POS/mobile/offline preparation, a suggested product walkthrough, and post-demo review. It labels all intentionally deferred integrations so the demonstration does not overstate current capability.

### Live Safety Boundaries

The live environment remains staging/demo first. Password rotation, cron creation, backups, asset uploads, server cache rebuilds, and browser smoke tests are deliberate Hostinger operator actions; they are not automated by the application. Database backups remain private, `.env` remains outside public paths and Git, and no destructive deployment command or unattended database-backup cron is introduced.

## Website CMS + SEO Admin Foundation

This foundation extends the Command Center CMS without changing the separate public website application. `CmsPage` remains the canonical page record for both route-specific SEO pages and structured landing pages; `CmsArticle` is the dedicated, independently publishable article record. Existing CMS pages, menus, media, homepage sections, and settings remain intact.

### Admin Areas and Routes

- `/cms/seo-pages`: route-specific SEO content with title, H1, intro and footer copy, canonical and social metadata, JSON-LD schema validation, robots controls, sitemap controls, draft/published/scheduled/archived state, and revision history.
- `/cms/landing-pages`: structured campaign, product, industry, module, solution, location, and comparison pages. Content sections, FAQs, related product keys, related industry keys, and CTA fields are stored as validated structured JSON where flexible page composition is required.
- `/cms/articles`: publishable articles with excerpts, content, category, tags, canonical and schema metadata, publication lifecycle, and sitemap controls.
- `/cms/seo`: global defaults, robots, sitemap URL, public business contact details, social links, organization schema, and analytics/verification settings.
- `/cms/redirects`: company-scoped 301/302 redirect management with enablement and internal notes.

The CMS navigation and Module Registry expose SEO Pages, Landing Pages, Articles, and Redirects. The Command Center dashboard shows a compact Website Publishing widget for CMS-authorized users, while the existing Website Control Center adds article counts.

### Data Model and Services

Migration `2026_07_17_020001_add_marketing_seo_fields_to_cms_tables.php` extends `cms_pages`, `cms_seo_settings`, and `cms_redirects` with MySQL-safe indexes and fields needed for public website delivery. Migration `2026_07_17_020002_create_cms_articles_table.php` creates `cms_articles` with company-scoped slug uniqueness, status/published-date lookup, category lookup, media/user relationships, and soft deletes.

`CmsMarketingRepository`, `CmsMarketingPageService`, `CmsArticleService`, and `CmsRedirectService` keep command-center controllers small and company-scoped. Page service operations write a revision snapshot for each content lifecycle change and record CMS audit entries. Existing `AuditLogger` remains the audit system of record.

### Public Read API Contract

`PublicCmsService` and `PublicCmsController` expose a future public-site read surface under `/api/public/cms`. Available endpoints are SEO page lookup by path, landing page by slug, article list/detail, public settings, sitemap entries, enabled redirects, and robots settings. The API returns published content only and deliberately omits company, user, audit, and internal administration fields. It is rate-limited by the dedicated `public-cms` limiter and cached for ten minutes outside the testing environment.

### Current Limitations

- The public Next.js website is intentionally unchanged; a later integration will consume these read endpoints.
- Structured landing-page sections and FAQ payloads are stored as validated JSON because the supported section set is intentionally extensible. Static website settings remain normalized existing CMS settings.
- Scheduled pages persist their requested publish time and remain private until published. The automated scheduled-publishing runner is a future operational job.
- Sitemap and robots endpoints provide delivery data only. XML rendering, crawler-based broken-link detection, Search Console ingestion, and redirect-hit middleware remain future website or infrastructure integrations.

## Website Content Editor Lite

Website Content Editor Lite is an additive, tenant-scoped CMS surface for simple public website content. It deliberately does not replace the established `CmsPage`, landing-page, SEO, menu, or homepage-builder workflows. Those records remain in place for their current consumers; the Lite editor owns the dedicated `cms_content_*` tables and gives non-technical administrators a guided alternative for future website delivery.

### Editor Architecture

`CmsContentPage`, `CmsContentSection`, `CmsNavigationItem`, and `CmsFooterBlock` are the dedicated content records. Migration `2026_07_17_040001_create_cms_content_editor_tables.php` creates the normalized page, section, navigation, and footer tables with tenant, route, publication, ordering, and public-read indexes. Flexible repeatable content such as FAQ rows, testimonials, feature cards, statistics, and footer links uses validated JSON arrays only behind friendly repeatable forms; no editor screen exposes raw JSON or code fields.

`CmsContentPageRepository` and `CmsContentNavigationRepository` keep company-scoped queries out of controllers. `CmsContentEditorService` owns page lifecycle, default homepage sections, section ordering, enable/disable behavior, navigation/footer saves, human-readable content-health warnings, and audit entries. Navigation and footer blocks expose a simple numeric display order in addition to their automatic ordering. The existing `AuditLogger` remains the audit system of record.

### Admin Routes and Permissions

The CMS group exposes `/cms/content`, `/cms/content/pages`, `/cms/content/blocks`, `/cms/content/navigation`, and `/cms/content/footer`. Page detail supplies friendly page details, content-health warnings, guided section forms, repeatable item cards, section move/enable controls, archive confirmation, and an internal API preview. Navigation and footer screens use the same form-first approach. The CMS navigation includes direct links to Website Content, Content Navigation, and Content Footer.

`cms.content.view`, `cms.content.create`, `cms.content.update`, `cms.content.publish`, `cms.content.delete`, and `cms.navigation.manage` use the existing Administrator/Manager CMS role boundary. Footer management continues to use `cms.footer.manage`. Staff remain denied by the current CMS middleware and gates.

### Content and Delivery Contract

Supported page types are Home, Product, Solution, Industry, Module, Pricing, Contact, About, and Custom Landing. Supported section types include Hero, Feature Grid, Benefits, Product Highlights, Industry Use Cases, Module Details, FAQ, CTA, Testimonials, Statistics, Comparison, Footer SEO Content, and Custom Content. The demo seeder provides a draft home page with Hero, Product Highlights, Features, Industries, AI-Powered Benefits, Testimonials, FAQ, CTA, and Footer SEO sections, together with starter navigation and a company-description footer block.

`PublicCmsService` remains the single published-only public read model. The new rate-limited endpoints are `/api/public/cms/content/pages`, `/api/public/cms/content/page?path=/`, `/api/public/cms/content/page/{pageKey}`, `/api/public/cms/content/navigation`, and `/api/public/cms/content/footer`. They expose only published pages, enabled sections, and enabled navigation/footer records; they omit company IDs, user IDs, audit data, and internal record IDs. CMS content writes advance a content-only cache version, so those endpoints reflect publication, unpublication, reordering, enablement, navigation, and footer changes immediately instead of waiting for their existing ten-minute non-test cache entries to expire.

### Current Limitations

- The Lite editor prepares data for a future public-site adapter; it does not change the separate public website or render public web pages itself.
- Media URLs are currently entered as validated HTTPS or internal paths. Selecting assets directly from the existing Media Library is a future editor enhancement.
- Nested navigation is represented by parent links and ordered records, but drag-and-drop and mega-menu rendering are intentionally deferred.
- There is no visual WYSIWYG page renderer in this phase. The internal API preview shows the exact safe content shape a future website client will consume.

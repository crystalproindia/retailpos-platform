# RetailPOS Platform - Phase 1 / 1.5 / 1.6 / 2 Project Structure

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

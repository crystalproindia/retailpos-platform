# RetailPOS Platform - Phase 1 / 1.5 Project Structure

This document describes the Command Center foundation built in Phase 1 and the dynamic Module Registry foundation added in Phase 1.5.

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
- Sidebar modules such as CRM, POS, Inventory, Orders, Finance, Marketing, CMS, SEO, WhatsApp, Analytics, Reports, AI Assistant, Users, Branches, and Company currently route to foundation module screens.
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

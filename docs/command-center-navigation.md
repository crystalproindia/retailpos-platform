# Command Center Navigation

The Command Center sidebar and mobile drawer use the same `ModuleRegistry` and `config/modules.php` registry. There is no separate mobile menu to maintain.

## Completed groups

- **Sales & CRM**: CRM, sales invoices, POS terminal/history/registers, customers, promotions, inventory, and GST & Compliance.
- **Content Management**: the completed Website CMS workspace and its content tools.
- **System & Operations**: notifications, delivery logs, health, queues, scheduled tasks, and application diagnostics.
- **Administration**: settings, users, branches, email integration, and other existing administration pages.

GST & Compliance uses `compliance.gst.*` routes: dashboard, settings, notes, reports, exports, return periods, document series, e-way readiness, and filing guide. These are accountant-review tools only; no GST return, e-invoice, or e-way bill submission route is exposed.

## Access

Registry visibility mirrors the configured role matrix in `config/permissions.php`; destination routes keep their existing middleware and cannot be opened merely by seeing a link. Administrators see completed management modules, managers see the operational groups permitted by the existing gates, and sales users do not see GST administration or CMS management.

Run `php artisan retailpos:sync-permissions --dry-run` to audit the active code-defined permission matrix. This application uses configuration-defined Laravel gates rather than persisted permission records, so the command is idempotent and never changes users, roles, or custom assignments.

## Adding a module

Add only a completed index or dashboard route to `config/modules.php`; never add an entity-specific edit/show route. Set the parent, category, sort order, role visibility, and a real named route. Then verify the route with the registry test and ensure the destination retains its authorization middleware.

Google Calendar and Google Meet remain paused and are deliberately not included in this navigation registry.

## Purchase Finance

The Purchases group reuses the existing dashboard, suppliers, requests, orders, GRNs, and returns. It exposes working Purchase Invoices, Supplier Payments, Purchase Reports, and the Input GST Register. The shared registry powers both desktop and mobile navigation, and every item is protected by its matching capability gate.

## Navigation Boundary

Only completed routes are visible. Registry entries that still use the generic `modules.show` foundation screen remain disabled until their dedicated module route and authorization boundary exist. The current role model contains Administrator, Manager, Sales, and Staff only; Finance, Warehouse, Cashier, and Content/Marketing role matrices are future role-model work and are not inferred by the navigation layer.

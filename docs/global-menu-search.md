# Global Menu Search

## Architecture

Global Menu Search is a navigation-only command palette. `GlobalMenuSearchService` builds a small, server-rendered index from `ModuleRegistry::sidebarForUser()` and the existing `SaasNavigationRegistry`. It includes only menu label, route name, resolved URL, icon, breadcrumb, grouping, and configured aliases. It does not query customers, invoices, products, or any other business records.

The rendered index is searched entirely in `resources/js/app.js`; no request is made for each character entered. The index is rebuilt only when the authenticated layout is rendered. Duplicate route names are collapsed before reaching the browser.

## Permission and Tenant Boundaries

The Module Registry continues to be the source of truth for enabled state, sidebar visibility, role membership, parent-child structure, and optional module permissions. Search uses the new `sidebarForUser()` path, so disabled and inaccessible modules never enter the browser index. Shared SaaS navigation remains filtered through its established permissions. Direct routes retain their normal middleware and authorization checks.

The current module registry does not define outlet-specific menu items. If a future module adds a license key or outlet constraint, it must be filtered by the registry before it can appear in search. Search never uses or stores customer, invoice, product, or other sensitive record information.

## Aliases

Aliases live in `config/modules.php` under `navigation_search.aliases`. V1 includes bill/invoice, stock/inventory, item/product, party/customer-supplier, vendor/supplier, GST/compliance, barcode, staff/users-employees, branch-shop/outlet, purchase or sales bill, email, payment, and return language. Module-specific aliases are supported through `search_aliases`; Invoice Designs adds invoice template and bill design. Invoice Reminders adds payment reminder, invoice reminder, overdue, outstanding, due invoice, collection reminder, unpaid bill, final notice, and bill reminder. As with every module, aliases are available only after the registry has admitted the route for the current user.

Search is case-insensitive, supports partial and multi-word matching, and applies a small edit-distance tolerance for individual words of four or more characters. Results match label, route, breadcrumb, and aliases.

## Interaction and Accessibility

The desktop sidebar shows a compact search control. Mobile uses a header search icon and opens the same dialog as a touch-friendly bottom sheet. Command K on macOS and Control K elsewhere open the palette. Arrow keys change the active result, Enter opens it, Escape closes it, and Tab remains trapped inside the dialog. Focus returns to the opening control when closed. The dialog uses `role="dialog"`, `aria-modal`, labelled input controls, visible focus styling, and reduced-motion-aware animation.

## Recent Navigation

The browser stores at most five recent destinations in local storage under a user-and-company scoped key. Each entry contains only route, label, breadcrumb, and URL. On opening the palette, stale routes are removed by matching saved entries against the current permitted index. Users can clear the list. Search phrases and business record data are never stored.

## Limitations

- This phase is navigation search only. It intentionally does not search application data.
- The current index is rendered with the page. A future real-time menu administration feature should invalidate or refresh this layout index after registry changes.
- No concrete external search service or large client dependency is used.

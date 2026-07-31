# Navigation State And CRM Lead Master Data

## Sidebar state

The shared Command Center sidebar keeps lightweight browser-local state for each authenticated company and user. It restores desktop collapse state, scroll position, and expanded module groups. Mobile state is separate, so a small-screen visit does not alter a workstation layout.

The state key is `retailpos.sidebar.v2.{company_id}.{user_id}`. It contains no authorisation or route decisions: Laravel renders the authorised navigation registry on every request, and stale group identifiers are ignored. The active group is always expanded. Reduced-motion preferences use instant restoration.

## CRM master data

Management roles use **CRM Settings** for tenant-scoped lead statuses and sources. Statuses carry a pipeline stage, probability, display tone, active state, ordering, and one required default. Sources carry a description, display tone, active state, ordering, and an optional default.

The `2026_07_31_010000_add_defaults_to_crm_lead_master_data.php` migration adds `is_default` to both CRM master-data tables and promotes each tenant's existing `New` status where no default exists. It is safe against partially applied schema state.

Lead forms show active records only and preselect tenant defaults. Existing leads retain an inactive linked record while being edited. Used records cannot be deleted, but can be deactivated. Custom actions are recorded in the audit log.

## Deployment note

After the migration and cache commands, rebuild frontend assets and synchronize `retailpos-platform/public/build/` to `/home/u237933956/domains/app.retailpos.biz/public_html/build/`. Sidebar behavior lives in the compiled Vite JavaScript. The migration is forward-only in production: use a forward remediation migration if a post-release correction is ever needed rather than rolling back and dropping populated default-state columns.

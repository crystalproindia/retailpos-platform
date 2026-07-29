# SaaS Security

Platform SaaS capabilities require `is_platform_admin` in addition to the administrator role. Tenant administrators cannot gain platform access by changing a role. Tenant portal pages resolve data from the authenticated user's company only. White-label media is validated against the same tenant's CMS media library.

Subscription suspension and feature checks are server-side and rollout-gated. Public CMS APIs remain public content endpoints and are not used as tenant administration APIs.

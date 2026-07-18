# CMS Content Import

Use `php artisan cms:import-website-content path/to/manifest.json` to import website content as CMS drafts.

Options: `--dry-run`, `--update-existing`, `--publish`, and `--company=<id>`.

The manifest must be JSON and can contain `pages`, `case_studies`, and `settings`. Imports skip matching slugs unless `--update-existing` is supplied. Secrets, tokens, passwords, and API keys are ignored. A minimal manifest is:

```json
{
  "pages": [{
    "title": "About RetailPOS",
    "slug": "about",
    "page_type": "standard",
    "body_content": "Imported content is reviewed as a draft.",
    "sections": [{"section_key": "hero", "section_type": "hero", "title": "About RetailPOS", "is_active": true}]
  }],
  "case_studies": [],
  "settings": {"company_name": "RetailPOS"}
}
```

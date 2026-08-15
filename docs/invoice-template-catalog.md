# Invoice Template Catalog

All templates support both GST and No-GST presentation through the shared
server-authoritative document mode. A4, A5, and Thermal 80mm templates support
the private authorized-signature snapshot. Thermal 58mm intentionally omits
the image signature to preserve readable item and totals output on the narrow
roll.

The view column identifies the shared rendering family. The variant column is
the composition applied inside that family; variants change masthead,
metadata, table, totals, and footer treatment rather than applying a color
swap alone.

| Key | Name | Paper | Style | Tax modes | Signature | View | Variant |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `structured_gst_grid` | Classic GST | A4 | Classic | GST + No-GST | Yes | `structured-gst-grid` | Classic |
| `premium_elegant` | Premium Gradient | A4 | Premium | GST + No-GST | Yes | `premium-elegant` | Premium |
| `compact_detailed_gst` | Compact Retail | A4 | Retail | GST + No-GST | Yes | `compact-detailed-gst` | Compact |
| `modern_split_panel` | Contemporary Split | A4 | Modern | GST + No-GST | Yes | `modern-split-panel` | Split |
| `executive_corporate_gst` | Executive Navy | A4 | Corporate | GST + No-GST | Yes | `executive-corporate-gst` | Executive |
| `modern_blue_corporate` | Modern Blue Corporate | A4 | Corporate | GST + No-GST | Yes | `a4-corporate` | Blue |
| `bold_retail` | Bold Retail | A4 | Retail | GST + No-GST | Yes | `a4-retail` | Bold |
| `minimal_professional` | Minimal Professional | A4 | Minimal | GST + No-GST | Yes | `a4-minimal` | Minimal |
| `modern_orange` | Modern Orange | A4 | Modern | GST + No-GST | Yes | `a4-retail` | Orange |
| `dark_header` | Dark Header | A4 | Corporate | GST + No-GST | Yes | `a4-corporate` | Dark |
| `green_business` | Green Business | A4 | Retail | GST + No-GST | Yes | `a4-corporate` | Green |
| `elegant_purple` | Elegant Purple | A4 | Premium | GST + No-GST | Yes | `a4-minimal` | Purple |
| `corporate_split` | Corporate Split | A4 | Corporate | GST + No-GST | Yes | `a4-corporate` | Split |
| `premium_business` | Premium Business | A4 | Corporate | GST + No-GST | Yes | `a4-corporate` | Premium |
| `commercial_services` | Commercial Services | A4 | Professional | GST + No-GST | Yes | `a4-service` | Commercial |
| `consultation_minimal` | Consultation Minimal | A4 | Professional | GST + No-GST | Yes | `a4-service` | Consultation |
| `client_billing_modern` | Client Billing Modern | A4 | Professional | GST + No-GST | Yes | `a4-service` | Client |
| `freelancer_blue` | Freelancer Blue | A4 | Professional | GST + No-GST | Yes | `a4-service` | Freelancer |
| `creative_studio` | Creative Studio | A4 | Creative | GST + No-GST | Yes | `a4-creative` | Studio |
| `licensing_premium` | Licensing Premium | A4 | Creative | GST + No-GST | Yes | `a4-creative` | Licensing |
| `publishing_royalty` | Publishing Royalty | A4 | Creative | GST + No-GST | Yes | `a4-creative` | Publishing |
| `construction_blue` | Construction Blue | A4 | Industry | GST + No-GST | Yes | `a4-industry` | Construction |
| `contractor_red` | Contractor Red | A4 | Industry | GST + No-GST | Yes | `a4-industry` | Contractor |
| `medical_consultation` | Medical Consultation | A4 | Industry | GST + No-GST | Yes | `a4-industry` | Medical |
| `catering_modern` | Catering Modern | A4 | Industry | GST + No-GST | Yes | `a4-industry` | Catering |
| `rental_orange` | Rental Orange | A4 | Industry | GST + No-GST | Yes | `a4-industry` | Rental |
| `a5_consultation` | A5 Consultation | A5 | Professional | GST + No-GST | Yes | `a5` | Consultation |
| `a5_creative` | A5 Creative | A5 | Creative | GST + No-GST | Yes | `a5` | Creative |
| `a5_modern_retail` | A5 Modern Retail | A5 | Retail | GST + No-GST | Yes | `a5` | Modern |
| `a5_compact_gst` | A5 Compact GST | A5 | Classic | GST + No-GST | Yes | `a5` | GST |
| `a5_boutique` | A5 Boutique | A5 | Premium | GST + No-GST | Yes | `a5` | Boutique |
| `a5_professional` | A5 Professional | A5 | Corporate | GST + No-GST | Yes | `a5` | Professional |
| `a5_bold` | A5 Bold | A5 | Retail | GST + No-GST | Yes | `a5` | Bold |
| `a5_minimal` | A5 Minimal | A5 | Minimal | GST + No-GST | Yes | `a5` | Minimal |
| `a5_service_invoice` | A5 Service Invoice | A5 | Modern | GST + No-GST | Yes | `a5` | Service |
| `thermal_80_classic` | Thermal Classic | Thermal 80mm | Classic | GST + No-GST | Yes | `thermal` | Classic |
| `thermal_80_modern` | Thermal Modern | Thermal 80mm | Modern | GST + No-GST | Yes | `thermal` | Modern |
| `thermal_80_compact` | Thermal Compact | Thermal 80mm | Minimal | GST + No-GST | Yes | `thermal` | Compact |
| `thermal_80_gst_detailed` | Thermal GST Detailed | Thermal 80mm | Classic | GST + No-GST | Yes | `thermal` | GST |
| `thermal_58_mini` | Thermal Mini | Thermal 58mm | Minimal | GST + No-GST | No | `thermal` | Mini |
| `thermal_58_essential` | Thermal Essential | Thermal 58mm | Modern | GST + No-GST | No | `thermal` | Essential |
| `thermal_58_gst_compact` | Thermal GST Compact | Thermal 58mm | Classic | GST + No-GST | No | `thermal` | GST Compact |
| `thermal_80_service` | Thermal Service | Thermal 80mm | Professional | GST + No-GST | Yes | `thermal` | Service |
| `thermal_58_retail` | Thermal Retail | Thermal 58mm | Retail | GST + No-GST | No | `thermal` | Retail |

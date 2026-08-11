# Customer, Invoice, and Product Image UX

## Scope

This phase connects the existing CRM customer, sales invoice, and inventory product workflows. It does not introduce a second customer ledger, invoice engine, or media library.

## Customer to Invoice

Authorized users can start a sales invoice from the CRM customer list or customer profile. The existing `sales.invoices.create` route accepts a `customer` query parameter and resolves it through `CrmCustomerRepository`, preserving company and Sales-user ownership rules.

The invoice form receives the selected customer identifier and a billing snapshot containing the customer's name, company, email, phone, billing address, country, and GSTIN. The submitted customer is resolved again on the server before the invoice is saved. A missing or unauthorized customer returns a not-found response and cannot be attached by changing the request payload.

Customer profiles show recent invoices, the last invoice and payment, and current outstanding value through the existing authorized invoice query. The View All Invoices action applies the customer filter to the existing invoice list.

## Invoice Customer Search

The invoice editor provides a search-first selector for customer name, company, phone, email, or GSTIN. Results are bounded to ten records and contain only the details needed to identify and bill the customer. The selected-customer card includes the current outstanding value calculated from authorized invoice records.

Clearing a customer returns the invoice to the existing walk-in billing flow. Selecting or clearing a customer does not change invoice line items or other draft form state.

## Quick Customer Creation

The New Customer drawer creates a CRM customer without leaving the invoice. Name is required; company, phone, email, GSTIN, billing address, customer type, and notes are optional. Validation errors remain in the drawer, and successful creation immediately selects the new customer.

`CrmCustomerService` owns the quick-create transaction, customer numbering, primary-contact creation, duplicate email/phone check, and audit entry. Sales users can subsequently access customers and invoices they created, while their existing assigned-lead visibility remains intact.

## Product Image Storage

The existing nullable `products.image` field stores the primary image path, so no migration or duplicate media relationship is required. `ProductImageService` stores files on Laravel's private local disk under:

```text
companies/{company_id}/products/{product_id}/{generated-uuid}.{extension}
```

Images are delivered through the authenticated `inventory.products.image` route. The product repository enforces company ownership before the service verifies the path prefix and file existence. Responses use private caching and `X-Content-Type-Options: nosniff`. Remote URLs, caller-selected paths, SVG, and base64 database payloads are not accepted.

Replacing an image removes the previous owned original and thumbnail only after the new original and thumbnail are stored. Removing an image clears the model path and deletes only files inside the product's expected tenant directory. Missing or legacy paths return a clean placeholder in the UI.

## Image Validation

- Formats: PNG, JPEG, and WebP
- Maximum file size: 2 MB
- Dimensions: 32 to 8,000 pixels on each side
- Validation: file, decoded image, extension, and server-detected MIME type
- Filename: generated UUID with a server-selected extension
- Runtime: PHP GD extension is required for thumbnail generation

## Image Reuse

Uploads generate a proportional thumbnail whose longest edge is at most 320 pixels. The secured thumbnail URL is reused on product lists, desktop and mobile POS product cards, global stock lookup, product stock details, transfer product search, transfer selection and receiving details, and stock-count lines. The original is reserved for product forms and profiles. Repeated thumbnails lazy-load, and a text initial is shown when no valid image is available.

## Permissions

- `crm.customers.view` controls customer profile and history access.
- `crm.customers.create` and `sales.invoices.create` are both required for invoice quick-create.
- `sales.invoices.create` controls customer search and invoice creation.
- `inventory.products.view` controls authenticated product image delivery.
- `inventory.products.image.manage` controls image upload, replacement, and removal and follows the existing inventory management roles.

Company scoping is enforced in repositories and repeated at mutation boundaries. Existing outlet authorization continues to govern invoice access and history.

## Audit Events

- `crm.invoice.created_from_customer`
- `crm.customer.quick_created_from_invoice`
- `crm.invoice.customer_changed`
- `inventory.product.image_uploaded`
- `inventory.product.image_replaced`
- `inventory.product.image_removed`

Audit metadata contains identifiers and file characteristics only. Image binary data and unnecessary customer PII are not recorded.

## Responsive UX

Customer actions wrap into accessible touch targets. The invoice selector remains near the billing header, and the customer drawer uses a full-height mobile layout with overlay and Escape-key dismissal. Product previews have stable dimensions and never stretch their surrounding form or operational cards.

## Known Limitations

- Recent-customer suggestions are not shown before a search; this keeps the endpoint bounded and avoids exposing unnecessary customer data.
- CRM commercial history covers CRM invoices and their payments. POS customer purchases and Phase Q returns use a separate operational customer model and are not merged into this CRM view.
- One primary product image and one operational thumbnail are supported. Galleries, additional responsive sizes, and CDN delivery remain future work.
- Images are served through authorized Laravel responses rather than a public CDN; this prioritizes tenant privacy over public catalogue delivery.
- Barcode label previews remain text and barcode focused because a thumbnail would reduce label readability at common print sizes.

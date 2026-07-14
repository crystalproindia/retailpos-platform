# RetailPOS Demo Readiness

## Before the client session

- [ ] Rotate the live Administrator password with `retailpos:admin-password`; do not leave `admin@retailpos.test` / `password` active.
- [ ] Confirm demo company details, logo, receipt footer, and visible branding are presentable.
- [ ] Review product names, prices, stock, customer records, and sample media for client-facing suitability.
- [ ] Confirm no test credentials, passwords, or internal notes are visible in the selected demo flows.
- [ ] Confirm the intended modules are enabled and incomplete/sensitive modules are not part of the presentation.
- [ ] Run `retailpos:live-check` and resolve all `FAIL` results.
- [ ] Complete the live smoke-test checklist in `DEPLOYMENT.md`.

## POS and offline preparation

- [ ] Confirm the desktop terminal and browser fullscreen control work on the demo device.
- [ ] Confirm the mobile POS and PWA installation behavior on a supported mobile browser.
- [ ] Prepare a customer with a mobile number, loyalty details, and purchase history for lookup.
- [ ] Confirm regular, frequent, recent, last-purchased, and add-on product suggestions are visible where demo data supports them.
- [ ] Prepare a short-offline demonstration plan: initialise online first, queue a permitted cash bill during a simulated short outage, then restore connectivity and show `/pos/offline`.
- [ ] Explain that Card/UPI references are cashier-entered foundations and that live gateways, terminals, wallet settlement, credit, and accounting are not connected.

## Suggested demo flow

1. Dashboard: show the current Command Center overview.
2. Inventory: show product availability and stock context.
3. Customers: look up a prepared customer by mobile number.
4. POS: add products and show rule-based suggestions.
5. Checkout: open the payment modal and complete a demo cash payment.
6. Receipt: show the completed bill and browser-print layout.
7. Offline POS: queue a prepared short-outage cash bill and show its later sync record.
8. CMS: show the managed content workspace without changing production copy during the session.

## After the session

- [ ] Review `/pos/offline` for failed or warning records created during the demo.
- [ ] Review `storage/logs/laravel.log` and the Operations Monitor for unexpected errors.
- [ ] Remove or clearly label any extra demo transactions if the staging data needs cleanup.
- [ ] Back up the database before making follow-up structural changes.

## Deliberate limits

This is a staging/demo environment. It does not provide live payment gateways, UPI/card terminal integrations, thermal-printer drivers, finance/accounting, WhatsApp/SMS sending, AI, or n8n. Offline POS is for short outages and server sync remains authoritative.

# Purchase Finance Permissions

The existing administrator and manager purchase roles can view, create, verify, and approve purchase invoices and record supplier payments. Payment reversal and GRN quantity override remain administrator-only safeguards.

New capabilities are `purchase-invoices.*`, `supplier-payments.*`, `purchase-reports.*`, and `input-gst-reports.*`. They are registered through the existing configuration-driven permission synchronisation mechanism and do not modify custom role assignments.

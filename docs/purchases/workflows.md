# Purchase Workflows

1. Receive and post a GRN through the existing GRN workflow.
2. Select accepted GRN lines when creating a purchase invoice. Partial invoicing is supported; excess quantity is rejected unless the administrator has the explicit override capability.
3. Save the invoice as draft, verify it, then approve it. Only approved invoices contribute to the payable and may receive payment allocations.
4. Record a supplier payment with an idempotency key. Allocate it across one or more approved invoices, or leave it unallocated as an advance.
5. An administrator can reverse a payment with a reason. Reversal removes allocations transactionally and restores invoice outstanding values.
6. An invoice with allocated payments must be unallocated through payment reversal before it can be cancelled.

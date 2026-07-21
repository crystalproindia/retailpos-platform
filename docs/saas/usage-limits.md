# Usage Limits

The authoritative `UsageService` counts active users, active branches, active warehouses, products, current-month invoices, and current-month completed POS sales. Snapshot recalculation is repeatable and uses one row per company and metric.

New product, warehouse, invoice, and completed POS-sale creation is checked in the real transactional path. Existing data remains readable after a downgrade; limits only prevent new creation. Company-row locking serializes concurrent checks in transactional callers. Branch and user management do not currently expose separate creation or invitation endpoints; their limits are available through the central service for those future paths.

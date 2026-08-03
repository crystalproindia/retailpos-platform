# Phase O: Explainable Forecasting and Retail Insights

Phase O is a tenant-scoped, deterministic advisory layer. It uses completed operational records already held by RetailPOS; it does not call an external AI provider, create purchase orders, change stock or prices, change CRM status, send communications, or rank employees.

## Data readiness

Sales forecasts require at least 14 completed POS sales days by default. Voided sales are excluded. Inventory forecasts use available stock plus the last 28 days of completed product-sale quantities. Customer segments use existing recency, order-count, and purchase-value foundations. CRM priority uses overdue follow-ups and staff-entered conversation ratings. Missing history produces `insufficient_data`, never a fabricated forecast.

## Methods and confidence

Sales forecasts use a weighted moving average: 40% historical daily average and 60% recent seven-day average. The range expands with observed variation. Confidence is a plain-language data-sufficiency indicator, not statistical certainty. Inventory calculates sales velocity, days remaining, and a non-negative advisory quantity for the configured horizon plus safety-stock days.

Customer segments are transparent RFM-style labels: insufficient data, active, loyal, at risk, lapsed, or high value. CRM priorities are rule-based and explain whether an overdue task and staff-entered receptiveness, buying-interest, or urgency contributed. The system-calculated priority must not be confused with staff-entered ratings.

## Operations, privacy, and review

Scheduled refreshes are non-overlapping and queued: sales nightly, inventory nightly, customers weekly, CRM hourly. Manual refreshes also enqueue work and never calculate in the page request. A run is unique per tenant, type, algorithm version, and local training period; retries reuse the same run rather than duplicating it. Each run records algorithm version, source period, data points, status, and safe failure text. Insight evidence contains metrics and record IDs only, not email addresses, phone numbers, secrets, or customer message content. Administrators can run forecasts and manage settings; managers can review only results and insights for their assigned outlets; no forecast is public.

Review every advisory result against the supporting transactions before acting. V1 has no forecast-versus-actual evaluation, supplier-pending-order adjustment, category seasonality, or automated action. Those are intentional future extensions.

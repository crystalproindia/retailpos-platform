<?php

return [
    'algorithm_version' => 'deterministic-v1',
    'minimum_sales_history_days' => 14,
    'forecast_horizons' => [7, 30, 90],
    'safety_stock_days' => 7,
    'default_supplier_lead_time_days' => 7,
    'slow_moving_days' => 45,
    'dead_stock_days' => 90,
    'customer_active_days' => 30,
    'customer_at_risk_days' => 60,
    'customer_lapsed_days' => 120,
    'insight_retention_days' => 14,
    'scheduled_generation_enabled' => true,
];

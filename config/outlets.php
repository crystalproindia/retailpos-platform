<?php

return [
    'enabled' => filter_var(env('MULTI_OUTLET_ENABLED', true), FILTER_VALIDATE_BOOL),
    'setup_enabled' => filter_var(env('MULTI_OUTLET_SETUP_ENABLED', true), FILTER_VALIDATE_BOOL),
    'transfers_enabled' => filter_var(env('MULTI_OUTLET_TRANSFERS_ENABLED', true), FILTER_VALIDATE_BOOL),
    'reporting_enabled' => filter_var(env('MULTI_OUTLET_REPORTING_ENABLED', true), FILTER_VALIDATE_BOOL),
];

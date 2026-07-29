<?php

return [
    'enabled' => filter_var(env('STORE_SETUP_WIZARD_ENABLED', true), FILTER_VALIDATE_BOOL),
    'product_import_enabled' => filter_var(env('STORE_SETUP_PRODUCT_IMPORT_ENABLED', true), FILTER_VALIDATE_BOOL),
    'scanner_test_enabled' => filter_var(env('STORE_SETUP_SCANNER_TEST_ENABLED', true), FILTER_VALIDATE_BOOL),
    'recommendations_enabled' => filter_var(env('STORE_SETUP_RECOMMENDATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),
    'version' => 'v1',
    'subtypes' => [
        'fashion_apparel' => ['mens_clothing' => 'Men’s clothing', 'womens_clothing' => 'Women’s clothing', 'kids_clothing' => 'Kids’ clothing', 'innerwear' => 'Innerwear', 'uniforms' => 'Uniforms', 'multi_category_fashion' => 'Multi-category fashion'],
        'grocery_supermarket' => ['grocery' => 'Grocery', 'supermarket' => 'Supermarket', 'mini_mart' => 'Mini mart', 'organic_store' => 'Organic store', 'wholesale_grocery' => 'Wholesale grocery'],
        'electronics' => ['consumer_electronics' => 'Consumer electronics', 'home_appliances' => 'Home appliances', 'computer_accessories' => 'Computer accessories', 'electrical_goods' => 'Electrical goods'],
        'mobile_accessories' => ['mobile_phones' => 'Mobile phones', 'accessories' => 'Accessories', 'repairs_services' => 'Repairs and services', 'multi_brand_showroom' => 'Multi-brand showroom'],
        'pharmacy' => ['retail_pharmacy' => 'Retail pharmacy', 'medical_store' => 'Medical store', 'clinic_pharmacy' => 'Clinic pharmacy', 'batch_expiry_pharmacy' => 'Pharmacy with batch and expiry tracking'],
        'jewellery' => ['gold_jewellery' => 'Gold jewellery', 'silver_jewellery' => 'Silver jewellery', 'imitation_jewellery' => 'Imitation jewellery', 'multi_category_jewellery' => 'Multi-category jewellery'],
        'footwear' => ['mens_footwear' => 'Men’s footwear', 'womens_footwear' => 'Women’s footwear', 'kids_footwear' => 'Kids’ footwear', 'multi_category_footwear' => 'Multi-category footwear'],
        'general_retail' => ['general_store' => 'General store', 'department_store' => 'Department store', 'gift_store' => 'Gift store', 'multi_category_retail' => 'Multi-category retail'],
    ],
    'categories' => [
        'fashion_apparel' => ['Men', 'Women', 'Kids', 'Accessories'], 'grocery_supermarket' => ['Grocery', 'Beverages', 'Snacks', 'Personal Care', 'Household'], 'electronics' => ['Electronics', 'Accessories', 'Appliances', 'Cables and Chargers'], 'mobile_accessories' => ['Mobile Phones', 'Accessories', 'Repairs and Services'], 'pharmacy' => ['Medicines', 'Healthcare', 'Personal Care', 'Medical Devices'], 'jewellery' => ['Gold Jewellery', 'Silver Jewellery', 'Imitation Jewellery'], 'footwear' => ['Men', 'Women', 'Kids', 'Accessories'], 'general_retail' => ['General Merchandise', 'Household', 'Personal Care'], 'default' => ['General Merchandise', 'Accessories'],
    ],
    'invoice_templates' => ['thermal' => 'compact_detailed_gst', 'a4' => 'structured_gst_grid', 'both' => 'compact_detailed_gst', 'digital' => 'modern_split_panel', 'unsure' => 'structured_gst_grid'],
];

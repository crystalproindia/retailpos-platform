<?php

return [
    'homepage_sections' => [
        'hero' => ['name' => 'Hero', 'sort_order' => 10],
        'features' => ['name' => 'Features', 'sort_order' => 20],
        'benefits' => ['name' => 'Benefits', 'sort_order' => 30],
        'modules' => ['name' => 'Modules', 'sort_order' => 40],
        'industries' => ['name' => 'Industries', 'sort_order' => 50],
        'solutions' => ['name' => 'Solutions', 'sort_order' => 60],
        'pricing_cta' => ['name' => 'Pricing CTA', 'sort_order' => 70],
        'testimonials' => ['name' => 'Testimonials', 'sort_order' => 80],
        'partners' => ['name' => 'Partners', 'sort_order' => 90],
        'statistics' => ['name' => 'Statistics', 'sort_order' => 100],
        'faq' => ['name' => 'FAQ', 'sort_order' => 110],
        'final_cta' => ['name' => 'Final CTA', 'sort_order' => 120],
        'footer_cta' => ['name' => 'Footer CTA', 'sort_order' => 130],
    ],

    'settings' => [
        'website_name' => ['label' => 'Website Name', 'type' => 'text'],
        'tagline' => ['label' => 'Tagline', 'type' => 'text'],
        'default_meta' => ['label' => 'Default Meta', 'type' => 'textarea'],
        'default_og_image' => ['label' => 'Default OG Image', 'type' => 'media'],
        'logo' => ['label' => 'Logo', 'type' => 'media'],
        'dark_logo' => ['label' => 'Dark Logo', 'type' => 'media'],
        'favicon' => ['label' => 'Favicon', 'type' => 'media'],
        'email' => ['label' => 'Email', 'type' => 'email'],
        'phone' => ['label' => 'Phone', 'type' => 'text'],
        'whatsapp' => ['label' => 'WhatsApp', 'type' => 'text'],
        'address' => ['label' => 'Address', 'type' => 'textarea'],
        'business_hours' => ['label' => 'Business Hours', 'type' => 'textarea'],
        'google_map' => ['label' => 'Google Map', 'type' => 'url'],
    ],

    'menu_locations' => [
        'header' => 'Header Menu',
        'footer' => 'Footer Menu',
        'mega' => 'Mega Menu',
        'legal' => 'Legal Links',
    ],

    'media_types' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'svg' => ['image/svg+xml'],
        'pdf' => ['application/pdf'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
    ],
];

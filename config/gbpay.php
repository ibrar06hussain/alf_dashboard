<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GBPay API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GBPay payment gateway integration
    |
    */

    'api_username' => env('GBPAY_API_USERNAME', 'alfgb@gbpay.gov.pk'),
    'api_password' => env('GBPAY_API_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Outbound GBPay API Configuration (if calling GBPay API directly)
    |--------------------------------------------------------------------------
    */
    'api_url' => env('GBPAY_API_URL', 'https://api.gbpay.gov.pk'),
    'api_key' => env('GBPAY_API_KEY', ''),
    'api_secret' => env('GBPAY_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook_secret' => env('GBPAY_WEBHOOK_SECRET', ''),
];


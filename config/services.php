<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'survey_external' => [
        'base_url' => env('SURVEY_EXTERNAL_BASE_URL', ''),
        'username' => env('SURVEY_EXTERNAL_USERNAME', ''),
        'password' => env('SURVEY_EXTERNAL_PASSWORD', ''),
        'auth_path' => env('SURVEY_EXTERNAL_AUTH_PATH', 'auth/login'),
        'lookup_path' => env('SURVEY_EXTERNAL_LOOKUP_PATH', 'solar/member/lookup'),
    ],

    'power_dept_reference_api' => [
        'base_url' => env('POWER_DEPT_BASE_URL', ''),
        'username' => env('POWER_DEPT_USERNAME', ''),
        'password' => env('POWER_DEPT_PASSWORD', ''),
    ],

];


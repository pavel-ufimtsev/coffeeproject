<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'retailcrm' => [
        // Account URL, e.g. https://coffeeproject.retailcrm.ru
        'url' => env('RETAILCRM_URL'),
        // Settings → Integration → API access keys
        'api_key' => env('RETAILCRM_API_KEY'),
        // Code of the store ("магазин") orders are created in, see `php artisan retailcrm:check`
        'site' => env('RETAILCRM_SITE'),
        // Send new shop orders to retailCRM. Off locally so test orders stay out of the live account.
        'push_orders' => (bool) env('RETAILCRM_PUSH_ORDERS', false),
    ],

    'tbank' => [
        // "test" or "live": which terminal below takes payments.
        'mode' => env('TBANK_MODE', 'test'),
        // Test terminals (keys ending in DEMO) use the same address as live ones.
        'url' => env('TBANK_API_URL', 'https://securepay.tinkoff.ru/v2'),
        // T-Business → Internet acquiring → Shops → Terminals
        'test' => [
            'terminal_key' => env('TBANK_TEST_TERMINAL_KEY'),
            'password' => env('TBANK_TEST_PASSWORD'),
        ],
        'live' => [
            'terminal_key' => env('TBANK_LIVE_TERMINAL_KEY'),
            'password' => env('TBANK_LIVE_PASSWORD'),
        ],
        // 54-ФЗ receipts through T-Bank's online cash register. Required once a register is connected to the terminal.
        'receipts' => (bool) env('TBANK_RECEIPTS', false),
        'ffd_version' => env('TBANK_FFD_VERSION', '1.2'),
        // osn, usn_income, usn_income_outcome, esn, patent
        'taxation' => env('TBANK_TAXATION', 'usn_income'),
        // none, vat0, vat5, vat7, vat10, vat22, vat105, vat107, vat110, vat122
        'vat' => env('TBANK_VAT', 'none'),
        // full_payment, or full_prepayment when goods ship after the payment
        'receipt_payment_method' => env('TBANK_RECEIPT_PAYMENT_METHOD', 'full_payment'),
    ],

];

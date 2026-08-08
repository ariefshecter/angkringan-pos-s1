<?php

return [

    'midtrans' => [
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_IS_3DS', true),
        'enabled_payments' => array_values(array_filter(array_map(
            'trim',
            explode(',', env(
                'MIDTRANS_ENABLED_PAYMENTS',
                'other_qris,bca_va,bni_va,bri_va,cimb_va,permata_va'
            ))
        ))),
        'bank_channels' => [
            'bca_va' => 'BCA',
            'bni_va' => 'BNI',
            'bri_va' => 'BRI',
            'cimb_va' => 'CIMB Niaga',
            'permata_va' => 'Permata',
            'other_bank_va' => 'Bank Lainnya',
        ],
        'core_bank_codes' => [
            'bca_va' => 'bca',
            'bni_va' => 'bni',
            'bri_va' => 'bri',
            'cimb_va' => 'cimb',
            'permata_va' => 'permata',
            // Bank lain membayar melalui transfer antarbank ke Permata VA.
            'other_bank_va' => 'permata',
        ],
        'bank_code_labels' => [
            'bca' => 'BCA',
            'bni' => 'BNI',
            'bri' => 'BRI',
            'cimb' => 'CIMB Niaga',
            'permata' => 'Permata',
        ],
    ],

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

];

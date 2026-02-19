<?php

return [
    // 画面URLのプレフィックス
    'route_prefix' => 'procurement',

    // 画面アクセスのミドルウェア
    'middleware' => ['web', 'auth'],

    'enabled' => true,

    // API 認証設定
    'api_key' => env('PROCUREMENT_API_KEY'),
    'api_middleware' => ['api', \Lastdino\ProcurementFlow\Http\Middleware\VerifyApiKey::class],

    'mail' => [
        // Override the sender for procurement mails (optional)
        // If 'address' is empty or not set, Laravel's global mail.from is used.
        'from' => [
            // When true and a requester exists with an email, the requester will be used as the From address
            // Precedence: requester (when enabled) > package from > global mail.from
            'use_requester' => env('PROCUREMENT_MAIL_FROM_USE_REQUESTER', false),

            'address' => env('PROCUREMENT_MAIL_FROM_ADDRESS'),
            'name' => env('PROCUREMENT_MAIL_FROM_NAME', 'Procurement'),
        ],
    ],

    // monox integration settings
    'monox' => [
        'base_url' => env('PROCUREMENT_MONOX_API_BASE_URL', env('MONOX_API_BASE_URL')),
        'api_key' => env('PROCUREMENT_MONOX_API_KEY', env('MONOX_API_KEY')),
    ],

];

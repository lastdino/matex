<?php

return [
    // 画面URLのプレフィックス
    'route_prefix' => 'matex',

    // 画面アクセスのミドルウェア
    'middleware' => ['web', 'auth'],

    'enabled' => true,

    'table_prefix' => 'matex_',

    // 部門（Department）モデルが使用するテーブル名
    // 未設定の場合は table_prefix + 'departments' が使用されます。
    // monox 側のテーブルを直接参照したい場合などにここを指定します。
    'departments_table' => env('MATEX_DEPARTMENTS_TABLE'),

    // API 認証設定
    'api_key' => env('MATEX_API_KEY'),
    'api_middleware' => ['api', \Lastdino\Matex\Http\Middleware\VerifyApiKey::class],

    'mail' => [
        // Override the sender for procurement mails (optional)
        // If 'address' is empty or not set, Laravel's global mail.from is used.
        'from' => [
            // When true and a requester exists with an email, the requester will be used as the From address
            // Precedence: requester (when enabled) > package from > global mail.from
            'use_requester' => env('PROCUREMENT_MAIL_FROM_USE_REQUESTER', false),

            'address' => env('PROCUREMENT_MAIL_FROM_ADDRESS'),
            'name' => env('PROCUREMENT_MAIL_FROM_NAME', 'matex'),
        ],
    ],

    // monox integration settings
    'monox' => [
        'base_url' => env('MATEX_MONOX_API_BASE_URL', env('APP_URL')),
        'api_key' => env('MATEX_MONOX_API_KEY', env('MONOX_API_KEY')),
    ],

];

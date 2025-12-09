<?php

return [
    // 画面URLのプレフィックス
    'route_prefix' => 'procurement',

    // 画面アクセスのミドルウェア
    'middleware' => ['web', 'auth'],

    'enabled' => true,

    // GHS pictogram image configuration (package default)
    'ghs' => [
        // Storage disk name for GHS label images
        'disk' => 'public',

        // Directory under the disk where images live
        'directory' => 'ghs_labels',

        // Map GHS keys to filenames (you can add or change freely)
        // You may use bmp/png/jpg as needed.
        'map' => [
            'GHS01' => 'GHS01.bmp',
            'GHS02' => 'GHS02.bmp',
            'GHS03' => 'GHS03.bmp',
            'GHS04' => 'GHS04.bmp',
            'GHS05' => 'GHS05.bmp',
            'GHS06' => 'GHS06.bmp',
            'GHS07' => 'GHS07.bmp',
            'GHS08' => 'GHS08.bmp',
            'GHS09' => 'GHS09.bmp',
        ],

        // Placeholder image filename when a key is unknown or file is missing.
        // Set to null to hide instead of showing a placeholder.
        'placeholder' => null,
    ],
];

<?php

declare(strict_types=1);

return [
    'title' => 'Scan & Order',
    'back' => 'Back to list',

    'token' => [
        'title' => 'Token',
        'placeholder' => 'Enter token',
        'lookup' => 'Lookup',
    ],

    'info' => [
        'title' => 'Information',
        'material' => 'Material',
        'supplier' => 'Supplier',
        'unit_purchase' => 'Unit (purchase)',
        'moq' => 'MOQ',
        'pack_size' => 'Pack size',
    ],

    'options' => [
        'title' => 'Options',
    ],

    'qty' => [
        'label' => 'Order Quantity',
    ],

    'create_draft' => 'Create draft PO',

    'common' => [
        'not_set' => 'Not set',
    ],

    'messages' => [
        'invalid_or_expired_token' => 'Token is invalid or expired.',
        'material_not_found' => 'Material not found.',
        'recognized_enter_qty' => 'Token recognized. Please enter quantity.',
        'draft_created' => 'Draft purchase order created.',
        'order_failed' => 'Order failed: :message',
    ],
];

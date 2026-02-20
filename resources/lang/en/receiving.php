<?php

declare(strict_types=1);

return [
    'title' => 'Scan & Receive',
    'back_to_list' => 'Back to list',
    'token' => 'Token',
    'token_placeholder' => 'Enter token',
    'lookup' => 'Lookup',
    'info' => 'Information',
    'info_po' => 'PO',
    'info_material' => 'Material',
    'info_ordered_base' => 'Ordered (base)',
    'info_remaining_base' => 'Remaining (base)',
    'qty_received' => 'Quantity Received',
    'reference_number' => 'Reference No. (optional)',
    'lot_section_title' => 'Lot Information (required)',
    'lot_no' => 'Lot No',
    'lot_no_placeholder' => 'e.g. L-20251205-01',
    'mfg_date' => 'MFG Date (optional)',
    'expiry_date' => 'Expiry Date (optional)',
    'lot_notice' => 'This material is lot-controlled. Lot number is required (inventory is recorded by lot).',
    'receive' => 'Receive',

    'buttons' => [
        'go_to_receiving' => 'Go to Receiving',
    ],

    'messages' => [
        'token_not_found' => 'Token not found.',
        'not_receivable_status' => 'This purchase order is not receivable.',
        'shipping_line_excluded' => 'Shipping line cannot be received.',
        'recognized_enter_qty_adhoc' => 'Token recognized (ad‑hoc). Please enter quantity to receive.',
        'recognized_enter_qty' => 'Token recognized. Please enter quantity to receive.',
        'received_success' => 'Receiving completed.',
        'receive_failed' => 'Receiving failed: :message',
    ],
];

<?php

declare(strict_types=1);

return [
    'options' => [
        'title' => 'Options Settings',
        'back' => 'Back',

        'groups' => [
            'heading' => 'Groups',
            'add' => 'Add',
            'search_placeholder' => 'Search group...',
            'sort' => 'Sort',
            'inactive' => 'inactive',
            'buttons' => [
                'up' => 'Up',
                'down' => 'Down',
                'edit' => 'Edit',
                'enable' => 'Enable',
                'disable' => 'Disable',
            ],
            'modal' => [
                'title_create' => 'Create Group',
                'title_edit' => 'Edit Group',
                'name' => 'Name',
                'description' => 'Description',
                'active' => 'Active',
                'sort_order' => 'Sort Order',
                'cancel' => 'Cancel',
                'save' => 'Save',
            ],
        ],

        'items' => [
            'heading' => 'Options',
            'group_hint' => 'Group: :name',
            'add' => 'Add',
            'search_placeholder' => 'Search option...',
            'select_group_warning' => 'Please select a group to manage options.',
            'table' => [
                'code' => 'Code',
                'name' => 'Name',
                'sort' => 'Sort',
                'status' => 'Status',
                'actions' => 'Actions',
                'status_deleted' => 'deleted',
                'status_active' => 'active',
                'status_inactive' => 'inactive',
            ],
            'buttons' => [
                'up' => 'Up',
                'down' => 'Down',
                'edit' => 'Edit',
                'enable' => 'Enable',
                'disable' => 'Disable',
                'restore' => 'Restore',
                'delete' => 'Delete',
            ],
            'modal' => [
                'title_create' => 'Create Option',
                'title_edit' => 'Edit Option',
                'code' => 'Code',
                'name' => 'Name',
                'description' => 'Description',
                'active' => 'Active',
                'sort_order' => 'Sort Order',
                'cancel' => 'Cancel',
                'save' => 'Save',
            ],
        ],
    ],

    'approval' => [
        'title' => 'Approval Settings',
        'select' => [
            'label' => 'Approval flow for Purchase Orders',
            'placeholder' => 'Not selected (no approval flow)',
        ],
        'buttons' => [
            'save' => 'Save',
        ],
        'flash' => [
            'saved' => 'Approval settings saved.',
        ],
    ],

    'taxes' => [
        'title' => 'Tax Settings',
        'back' => 'Back',
        'items' => [
            'heading' => 'Item Tax',
            'default_rate' => 'Default Rate (default_rate)',
            'additional_rates' => [
                'heading' => 'Additional Rates (rates)',
                'add' => 'Add',
                'key' => 'Key',
                'rate' => 'Rate',
                'remove' => 'Remove',
            ],
            'schedule' => [
                'label' => 'Future Schedule (JSON)',
                'help' => 'It is okay to leave empty.',
            ],
        ],
        'shipping' => [
            'heading' => 'Shipping',
            'taxable' => 'Apply tax to shipping',
            'tax_rate' => 'Shipping tax rate',
        ],
        'buttons' => [
            'save' => 'Save',
        ],
        'flash' => [
            'saved' => 'Tax settings saved.',
        ],
        'errors' => [
            'invalid_json' => 'Invalid JSON format.',
        ],
    ],

    'display' => [
        'title' => 'Display & Currency Settings',
        'back' => 'Back',
        'decimals' => [
            'heading' => 'Number of Decimals',
            'qty' => 'Quantity (qty)',
            'unit_price' => 'Unit Price (unit_price)',
            'unit_price_materials' => 'Unit Price (materials list) (unit_price_materials)',
            'line_total' => 'Line Total (line_total)',
            'subtotal' => 'Subtotal (subtotal)',
            'tax' => 'Tax Amount (tax)',
            'total' => 'Grand Total (total)',
            'percent' => 'Percent (percent)',
        ],
        'currency' => [
            'heading' => 'Currency',
            'symbol' => 'Symbol',
            'position' => 'Position',
            'prefix' => 'Prefix',
            'suffix' => 'Suffix',
            'space' => 'Insert a space between symbol and number',
            'space_hint' => 'Example: "$ 1,234" when enabled',
        ],
        'buttons' => [
            'save' => 'Save',
        ],
        'flash' => [
            'saved' => 'Display & Currency settings have been saved.',
        ],
    ],

    'categories' => [
        'title' => 'Material Categories',
        'new' => 'Add Category',
        'fields' => [
            'name' => 'Name',
        ],
        'empty' => 'No categories.',
        'edit_title' => 'Edit Category',
        'create_title' => 'Create Category',
        'flash' => [
            'created' => 'Category created.',
            'updated' => 'Category updated.',
            'deleted' => 'Category deleted.',
        ],
    ],

    'pdf' => [
        'title' => 'PDF Settings',
        'back' => 'Back',
        'company' => [
            'heading' => 'Company',
            'name' => 'Company Name',
            'tel' => 'TEL',
            'fax' => 'FAX',
            'address' => 'Address (multi-line)',
        ],
        'texts' => [
            'heading' => 'Texts',
            'payment_terms' => 'Payment Terms',
            'delivery_location' => 'Delivery Location',
            'footnotes' => 'Footnotes (multi-line)',
        ],
        'buttons' => [
            'save' => 'Save',
        ],
        'flash' => [
            'saved' => 'PDF settings saved.',
        ],
    ],

    'tokens' => [
        'title' => 'Ordering Tokens',
        'to_labels' => 'Labels',
        'filters' => [
            'search_placeholder' => 'Search token / material name / SKU',
            'all_materials' => 'All materials',
            'enabled_all' => 'Enabled: All',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ],
        'table' => [
            'token' => 'Token',
            'material' => 'Material',
            'unit_qty' => 'Unit/Qty',
            'expires' => 'Expires',
            'actions' => 'Actions',
            'empty' => 'No tokens.',
        ],
        'labels' => [
            'id' => 'ID',
            'unit' => 'Unit',
            'default_qty' => 'Default',
        ],
        'buttons' => [
            'new' => 'New Token',
            'edit' => 'Edit',
            'enable' => 'Enable',
            'disable' => 'Disable',
            'delete' => 'Delete',
            'cancel' => 'Cancel',
            'save' => 'Save',
        ],
        'modal' => [
            'title_create' => 'Create Token',
            'title_edit' => 'Edit Token',
            'token' => 'Token',
            'material' => 'Material',
            'select_placeholder' => 'Please select',
            'unit_purchase' => 'Unit (purchase)',
            'default_qty' => 'Default Qty',
            'enabled' => 'Enabled',
            'expires_at' => 'Expires At',
        ],
    ],

    'labels' => [
        'title' => 'Shelf Labels (QR)',
        'to_tokens' => 'Manage Tokens',
        'filters' => [
            'search_placeholder' => 'Search token / material name / SKU',
            'all_materials' => 'All materials',
            'payload' => 'Payload',
            'payload_token_only' => 'Token only',
            'payload_url' => 'URL',
            'per_page' => 'Per page',
        ],
        'card' => [
            'unit' => 'Unit',
            'moq_and_pack' => 'MOQ: :moq / Pack: :pack',
        ],
        'buttons' => [
            'print' => 'Print / Export PDF',
        ],
    ],
];

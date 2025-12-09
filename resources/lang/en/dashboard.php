<?php

declare(strict_types=1);

return [
    'cards' => [
        'open_pos' => 'Open POs',
        'this_month_total' => 'This Month PO Total',
        'low_stock_materials' => 'Low Stock Materials',
        'critical' => 'Critical',
        'overdue_pos' => 'Overdue POs',
        'incoming_7d' => 'Incoming 7 Days',
        'otif_30d' => 'OTIF (30d)',
        'otif' => [
            'on_time_full' => 'On-Time & Full: :on / :total',
        ],
    ],
    'low_stock' => [
        'title' => 'Low Stock Alerts',
        'table' => [
            'sku' => 'SKU',
            'name' => 'Name',
            'stock' => 'Stock',
            'safety' => 'Safety',
        ],
        'lot_badge' => 'Lot',
        'empty' => 'No alerts',
    ],
    'top_suppliers' => [
        'title' => 'Top Suppliers (30d)',
        'empty' => 'No data',
    ],
    'spend_trend' => [
        'title' => 'Spend Trend (12w)',
        'points' => 'Points: :count',
    ],
];

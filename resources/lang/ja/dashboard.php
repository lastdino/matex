<?php

declare(strict_types=1);

return [
    'cards' => [
        'open_pos' => '未完了の発注',
        'this_month_total' => '今月の発注合計',
        'low_stock_materials' => '在庫不足の資材',
        'critical' => '重大',
        'overdue_pos' => '期限超過の発注',
        'incoming_7d' => '直近7日の入荷予定',
        'otif_30d' => 'OTIF（30日）',
        'otif' => [
            'on_time_full' => 'オンタイム＆フル: :on / :total',
        ],
    ],
    'low_stock' => [
        'title' => '在庫不足アラート',
        'table' => [
            'sku' => 'SKU',
            'name' => '名称',
            'stock' => '在庫',
            'safety' => '安全在庫',
        ],
        'lot_badge' => 'ロット',
        'empty' => 'アラートはありません',
    ],
    'top_suppliers' => [
        'title' => '上位サプライヤー（30日）',
        'empty' => 'データなし',
    ],
    'spend_trend' => [
        'title' => '支出トレンド（12週）',
        'points' => 'ポイント: :count',
    ],
];

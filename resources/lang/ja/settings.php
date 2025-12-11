<?php

declare(strict_types=1);

return [
    'options' => [
        'title' => 'オプション設定',
        'back' => '戻る',

        'groups' => [
            'heading' => 'グループ',
            'add' => '追加',
            'search_placeholder' => 'グループを検索...',
            'sort' => '並び順',
            'inactive' => '無効',
            'buttons' => [
                'up' => '上へ',
                'down' => '下へ',
                'edit' => '編集',
                'enable' => '有効化',
                'disable' => '無効化',
            ],
            'modal' => [
                'title_create' => 'グループを作成',
                'title_edit' => 'グループを編集',
                'name' => '名称',
                'description' => '説明',
                'active' => '有効',
                'sort_order' => '並び順',
                'cancel' => 'キャンセル',
                'save' => '保存',
            ],
        ],

        'items' => [
            'heading' => 'オプション',
            'group_hint' => 'グループ: :name',
            'add' => '追加',
            'search_placeholder' => 'オプションを検索...',
            'select_group_warning' => '管理するグループを選択してください。',
            'table' => [
                'code' => 'コード',
                'name' => '名称',
                'sort' => '並び順',
                'status' => '状態',
                'actions' => '操作',
                'status_deleted' => '削除済み',
                'status_active' => '有効',
                'status_inactive' => '無効',
            ],
            'buttons' => [
                'up' => '上へ',
                'down' => '下へ',
                'edit' => '編集',
                'enable' => '有効化',
                'disable' => '無効化',
                'restore' => '復元',
                'delete' => '削除',
            ],
            'modal' => [
                'title_create' => 'オプションを作成',
                'title_edit' => 'オプションを編集',
                'code' => 'コード',
                'name' => '名称',
                'description' => '説明',
                'active' => '有効',
                'sort_order' => '並び順',
                'cancel' => 'キャンセル',
                'save' => '保存',
            ],
        ],
    ],
    'approval' => [
        'title' => '承認フロー設定',
        'select' => [
            'label' => '発注書の承認フロー',
            'placeholder' => '未選択（承認フローなし）',
        ],
        'buttons' => [
            'save' => '保存',
        ],
        'flash' => [
            'saved' => '承認フロー設定を保存しました。',
        ],
    ],
    'taxes' => [
        'title' => '税設定',
        'back' => '戻る',
        'items' => [
            'heading' => 'アイテム税',
            'default_rate' => '標準税率 (default_rate)',
            'additional_rates' => [
                'heading' => '追加の税率 (rates)',
                'add' => '追加',
                'key' => 'キー',
                'rate' => '税率',
                'remove' => '削除',
            ],
            'schedule' => [
                'label' => '将来スケジュール (JSON)',
                'help' => '空のままでも構いません。',
            ],
        ],
        'shipping' => [
            'heading' => '送料',
            'taxable' => '送料に税を適用',
            'tax_rate' => '送料の税率',
        ],
        'buttons' => [
            'save' => '保存',
        ],
        'flash' => [
            'saved' => '税設定を保存しました。',
        ],
        'errors' => [
            'invalid_json' => 'JSON の形式が正しくありません。',
        ],
    ],

    'display' => [
        'title' => '表示・通貨設定',
        'back' => '戻る',
        'decimals' => [
            'heading' => '小数点桁数',
            'qty' => '数量 (qty)',
            'unit_price' => '単価 (unit_price)',
            'unit_price_materials' => '単価（資材一覧）(unit_price_materials)',
            'line_total' => '行小計 (line_total)',
            'subtotal' => '小計 (subtotal)',
            'tax' => '税額 (tax)',
            'total' => '合計 (total)',
            'percent' => 'パーセンテージ (percent)',
        ],
        'currency' => [
            'heading' => '通貨表示',
            'symbol' => '記号',
            'position' => '位置',
            'prefix' => '前置',
            'suffix' => '後置',
            'space' => '記号と数値の間にスペースを入れる',
            'space_hint' => '例: 有効時は "$ 1,234" のように表示',
        ],
        'buttons' => [
            'save' => '保存',
        ],
        'flash' => [
            'saved' => '表示・通貨設定を保存しました。',
        ],
    ],

    'categories' => [
        'title' => '資材カテゴリの設定',
        'new' => 'カテゴリを追加',
        'fields' => [
            'name' => '名称',
            'code' => 'コード',
        ],
        'empty' => 'カテゴリがありません',
        'edit_title' => 'カテゴリを編集',
        'create_title' => 'カテゴリを作成',
        'flash' => [
            'created' => 'カテゴリを作成しました。',
            'updated' => 'カテゴリを更新しました。',
            'deleted' => 'カテゴリを削除しました。',
        ],
    ],
    'pdf' => [
        'title' => 'PDF 設定',
        'back' => '戻る',
        'company' => [
            'heading' => '会社情報',
            'name' => '会社名',
            'tel' => 'TEL',
            'fax' => 'FAX',
            'address' => '住所（複数行）',
        ],
        'texts' => [
            'heading' => 'テキスト',
            'payment_terms' => '支払条件',
            'delivery_location' => '納入場所',
            'footnotes' => '脚注（複数行）',
        ],
        'buttons' => [
            'save' => '保存',
        ],
        'flash' => [
            'saved' => 'PDF 設定を保存しました。',
        ],
    ],
    'tokens' => [
        'title' => '発注トークン',
        'to_labels' => '棚ラベル',
        'filters' => [
            'search_placeholder' => 'トークン / 資材名 / SKU を検索',
            'all_materials' => 'すべての資材',
            'enabled_all' => '有効: すべて',
            'enabled' => '有効',
            'disabled' => '無効',
        ],
        'table' => [
            'token' => 'トークン',
            'material' => '資材',
            'unit_qty' => '単位/数量',
            'expires' => '有効期限',
            'actions' => '操作',
            'empty' => 'トークンはありません。',
        ],
        'labels' => [
            'id' => 'ID',
            'unit' => '単位',
            'default_qty' => '既定',
        ],
        'buttons' => [
            'new' => '新規トークン',
            'edit' => '編集',
            'enable' => '有効化',
            'disable' => '無効化',
            'delete' => '削除',
            'cancel' => 'キャンセル',
            'save' => '保存',
        ],
        'modal' => [
            'title_create' => 'トークン作成',
            'title_edit' => 'トークン編集',
            'token' => 'トークン',
            'material' => '資材',
            'select_placeholder' => '選択してください',
            'unit_purchase' => '単位（発注）',
            'default_qty' => '既定数量',
            'enabled' => '有効',
            'expires_at' => '有効期限',
        ],
    ],
    'labels' => [
        'title' => '棚ラベル（QR）',
        'to_tokens' => 'トークン管理へ',
        'filters' => [
            'search_placeholder' => 'トークン / 資材名 / SKU を検索',
            'all_materials' => 'すべての資材',
            'payload' => 'ペイロード',
            'payload_token_only' => 'トークンのみ',
            'payload_url' => 'URL',
            'per_page' => '1ページあたり',
        ],
        'card' => [
            'unit' => '単位',
            'moq_and_pack' => '最小発注量: :moq / 入数: :pack',
        ],
        'buttons' => [
            'print' => '印刷 / PDF出力',
        ],
    ],
];

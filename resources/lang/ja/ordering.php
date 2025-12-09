<?php

declare(strict_types=1);

return [
    'title' => 'スキャンして発注',
    'back' => '一覧へ戻る',

    'token' => [
        'title' => 'トークン',
        'placeholder' => 'トークンを入力してください',
        'lookup' => '照会',
    ],

    'info' => [
        'title' => '情報',
        'material' => '資材',
        'supplier' => 'サプライヤー',
        'unit_purchase' => '単位（発注）',
        'moq' => '最小発注量',
        'pack_size' => '入数（倍数）',
    ],

    'options' => [
        'title' => 'オプション',
    ],

    'qty' => [
        'label' => '発注数量',
    ],

    'create_draft' => 'ドラフト発注を作成',

    'common' => [
        'not_set' => '未設定',
    ],

    'messages' => [
        'invalid_or_expired_token' => 'トークンが無効か、期限切れです。',
        'material_not_found' => '資材が見つかりません。',
        'recognized_enter_qty' => 'トークンを認識しました。数量を入力してください。',
        'draft_created' => 'ドラフト発注を作成しました。',
        'order_failed' => '発注に失敗しました: :message',
    ],
];

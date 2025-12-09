<?php

declare(strict_types=1);

return [
    'title' => 'スキャン＆入荷',
    'back_to_list' => '一覧に戻る',
    'token' => 'トークン',
    'token_placeholder' => 'トークンを入力してください',
    'lookup' => '照会',
    'info' => '情報',
    'info_po' => 'PO',
    'info_material' => '資材',
    'info_remaining_base' => '残数（基準）',
    'qty_received' => '受入数量',
    'reference_number' => '伝票番号（任意）',
    'lot_section_title' => 'ロット情報（必須）',
    'lot_no' => 'Lot No',
    'lot_no_placeholder' => '例: L-20251205-01',
    'mfg_date' => '製造日（任意）',
    'expiry_date' => '有効期限（任意）',
    'lot_notice' => 'この資材はロット管理対象です。ロット番号の入力が必須です（在庫はロット別に記録されます）。',
    'receive' => '受入',

    'messages' => [
        'token_not_found' => 'トークンが見つかりません。',
        'not_receivable_status' => 'この発注は入荷対象ではありません。',
        'shipping_line_excluded' => '送料行は入荷対象外です。',
        'recognized_enter_qty_adhoc' => 'トークンを認識しました（アドホック）。入荷数量を入力してください。',
        'recognized_enter_qty' => 'トークンを認識しました。入荷数量を入力してください。',
        'received_success' => '入荷が完了しました。',
        'receive_failed' => '入荷に失敗しました: :message',
    ],
];

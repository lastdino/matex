<?php

namespace Lastdino\Matex\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Lastdino\Matex\Models\Receiving;

class ReceivingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Receiving  $receiving  入庫データ
     */
    public function __construct(public Receiving $receiving) {}

    public function build(): static
    {
        // 必要なリレーションを一括ロードしてDBアクセスを最適化
        $this->receiving->loadMissing([
            'purchaseOrder.supplier',
            'purchaseOrder.department',
            'items.material.category',
            'items.purchaseOrderItem.optionValues.group',
            'items.purchaseOrderItem.optionValues.option',
        ]);

        $poNumber = $this->receiving->purchaseOrder->po_number;

        return $this->subject("【入庫完了通知】発注番号: {$poNumber}")
            ->view('matex::mail.notifications.receiving');
    }
}

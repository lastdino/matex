<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use LastDino\ChromeLaravel\Facades\Chrome;

class PurchaseOrderIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $po)
    {
    }

    public function build(): static
    {
        // Prefer already-loaded relations, avoid unnecessary DB hits
        $po = $this->po->loadMissing(['supplier', 'items.material']);
        $poNumber = $po->po_number ?: ('Draft-' . $po->getKey());

        $blade = Blade::render('procflow::pdf.purchase-order', compact('po'));

        $tmpPath = Chrome::pdfFromHtml($blade, [
            'printBackground' => true,
        ]);

        return $this
            ->subject("【注文書】PO {$poNumber}")
            ->view('procflow::mail.purchase-orders.issued', [
                'po' => $po,
            ])
            ->attach($tmpPath, [
                'as'   => "PO-{$poNumber}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}

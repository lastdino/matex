<?php

declare(strict_types=1);

namespace Lastdino\Matex\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;
use LastDino\ChromeLaravel\Facades\Chrome;
use Lastdino\Matex\Models\PurchaseOrder;

class PurchaseOrderPdfController extends Controller
{
    public function __invoke(PurchaseOrder $po)
    {
        // Prevent download if status is draft or canceled
        if ($po->status === \Lastdino\Matex\Enums\PurchaseOrderStatus::Draft) {
            abort(403, __('matex::po.errors.draft_pdf_not_allowed') ?: 'Draft purchase orders cannot be downloaded as PDF.');
        }

        if ($po->status === \Lastdino\Matex\Enums\PurchaseOrderStatus::Canceled) {
            abort(403, __('matex::po.errors.canceled_pdf_not_allowed') ?: 'Canceled purchase orders cannot be downloaded as PDF.');
        }

        // Load missing relations to avoid N+1
        $po = $po->loadMissing(['supplier', 'items.material']);
        $poNumber = $po->po_number ?: ('Draft-'.$po->getKey());

        $html = Blade::render('matex::pdf.purchase-order', compact('po'));

        $tmpPath = Chrome::pdfFromHtml($html, [
            'printBackground' => true,
        ]);

        $filename = "PO-{$poNumber}.pdf";

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}

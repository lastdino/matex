<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Blade;
use LastDino\ChromeLaravel\Facades\Chrome;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;

class PurchaseOrderPdfController extends Controller
{
    public function __invoke(PurchaseOrder $po)
    {
        // Prevent download if status is draft
        if ($po->status === \Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus::Draft) {
            abort(403, __('procflow::po.errors.draft_pdf_not_allowed') ?: 'Draft purchase orders cannot be downloaded as PDF.');
        }

        // Load missing relations to avoid N+1
        $po = $po->loadMissing(['supplier', 'items.material']);
        $poNumber = $po->po_number ?: ('Draft-'.$po->getKey());

        $html = Blade::render('procflow::pdf.purchase-order', compact('po'));

        $tmpPath = Chrome::pdfFromHtml($html, [
            'printBackground' => true,
        ]);

        $filename = "PO-{$poNumber}.pdf";

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}

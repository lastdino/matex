<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lastdino\ProcurementFlow\Enums\PurchaseOrderStatus;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Services\PoNumberGenerator;

class PurchaseOrderIssueController extends Controller
{
    public function __invoke(PurchaseOrder $po, PoNumberGenerator $numbers): JsonResponse
    {
        abort_unless($po->status === PurchaseOrderStatus::Draft, 422, 'Only draft orders can be issued.');

        $issued = DB::transaction(function () use ($po, $numbers) {
            $po->po_number = $po->po_number ?: $numbers->generate(CarbonImmutable::now());
            $po->status = PurchaseOrderStatus::Issued;
            $po->issue_date = CarbonImmutable::now();
            $po->save();

            return $po->fresh(['items']);
        });

        return response()->json($issued);
    }
}

<?php

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Lastdino\ProcurementFlow\Models\Option;
use Lastdino\ProcurementFlow\Models\OptionGroup;
use Lastdino\ProcurementFlow\Models\PurchaseOrder;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Support\Settings;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithPagination;

    public string $q = '';

    public string $status = '';

    // Separate filters
    public string $poNumber = '';

    public string $supplierId = '';

    public string $requesterId = '';

    // Date range filters (YYYY-MM-DD)
    public array $issueDate = [
        'start' => null,
        'end' => null,
    ];

    public array $expectedDate = [
        'start' => null,
        'end' => null,
    ];

    // Receiving date range filters (YYYY-MM-DD)
    public array $receivingDate = [
        'start' => null,
        'end' => null,
    ];

    // Export modal state
    public bool $showExportModal = false;

    // Matrix options
    public ?int $rowGroupId = null;

    public ?int $colGroupId = null;

    public string $aggregateType = 'amount'; // amount|quantity

    // Modal state for creating a PO
    public bool $showPoModal = false;

    /** @var array{supplier_id:?int,expected_date:?string,items:array<int,array{material_id:?int,unit_purchase:?string,qty_ordered:float|int|null,price_unit:float|int|null,tax_rate:float|int|null,description:?string,desired_date:?string|null,expected_date:?string|null,options:array<int,int|null>}>} */
    public array $poForm = [
        'supplier_id' => null,
        'expected_date' => null,
        'items' => [
            ['material_id' => '', 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => null, 'description' => null, 'desired_date' => null, 'expected_date' => null, 'options' => []],
        ],
    ];

    // Modal state for creating an Ad-hoc PO (materials not registered)
    public bool $showAdhocPoModal = false;

    /** @var array{supplier_id:?int,expected_date:?string,items:array<int,array{description:string|null,manufacturer:string|null,unit_purchase:string,qty_ordered:float|int|null,price_unit:float|int|null,tax_rate:float|int|null,desired_date:?string|null,expected_date:?string|null,options:array<int,int|null>}>} */
    public array $adhocForm = [
        'supplier_id' => null,
        'expected_date' => null,
        'items' => [
            ['description' => null, 'manufacturer' => null, 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => null, 'desired_date' => null, 'expected_date' => null, 'options' => []],
        ],
    ];

    public function getOrdersProperty()
    {
        $q = (string) $this->q;
        $status = (string) $this->status;
        $poNumber = (string) $this->poNumber;
        $supplierId = (string) $this->supplierId;
        $requesterId = (string) $this->requesterId;
        $issueFrom = (string) $this->issueDate['start'];
        $issueTo = (string) $this->issueDate['end'];
        $expFrom = (string) $this->expectedDate['start'];
        $expTo = (string) $this->expectedDate['end'];

        return PurchaseOrder::query()
            ->with(['supplier', 'requester'])
            // Dedicated filters
            ->when($poNumber !== '', function ($query) use ($poNumber) {
                // Allow prefix/partial match for PO#
                $query->where('po_number', 'like', $poNumber.'%');
            })
            ->when($supplierId !== '', function ($query) use ($supplierId) {
                $query->where('supplier_id', (int) $supplierId);
            })
            ->when($requesterId !== '', function ($query) use ($requesterId) {
                $query->where('created_by', (int) $requesterId);
            })
            // Issue date range
            ->when($issueFrom !== '', function ($query) use ($issueFrom) {
                $query->whereDate('issue_date', '>=', $issueFrom);
            })
            ->when($issueTo !== '', function ($query) use ($issueTo) {
                $query->whereDate('issue_date', '<=', $issueTo);
            })
            // Expected date range
            ->when($expFrom !== '', function ($query) use ($expFrom) {
                $query->whereDate('expected_date', '>=', $expFrom);
            })
            ->when($expTo !== '', function ($query) use ($expTo) {
                $query->whereDate('expected_date', '<=', $expTo);
            })
            ->when($q !== '', function ($query) use ($q) {
                // キーワードを空白で分割して AND 条件を実現（案1）
                $keywords = preg_split('/\s+/u', trim((string) $q)) ?: [];

                if (count($keywords) > 1) {
                    // 複数語のときは、以下のフィールドに対して AND 検索：
                    // - materials.name（品名）
                    // - materials.manufacturer_name（メーカー名）
                    // - purchase_order_items.description（説明）
                    // - purchase_order_items.manufacturer（単発注文のメーカー名）
                    foreach ($keywords as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($and) use ($like) {
                            $and
                                // 資材マスタの品名／メーカー名
                                ->orWhereHas('items.material', function ($mq) use ($like) {
                                    $mq->where(function ($mm) use ($like) {
                                        $mm->where('name', 'like', $like)
                                            ->orWhere('manufacturer_name', 'like', $like);
                                    });
                                })
                                // 発注アイテムの説明／単発メーカー名
                                ->orWhereHas('items', function ($iq) use ($like) {
                                    $iq->where(function ($iqq) use ($like) {
                                        $iqq->where('description', 'like', $like)
                                            ->orWhere('manufacturer', 'like', $like);
                                    });
                                });
                        });
                    }
                } else {
                    // 単一語は従来の広い OR 検索を維持（UIの利便性維持）
                    $single = $keywords[0] ?? $q;
                    $query->where(function ($sub) use ($single) {
                        $sub->where('po_number', 'like', "%{$single}%")
                            ->orWhere('notes', 'like', "%{$single}%")
                            // サプライヤー名
                            ->orWhereHas('supplier', function ($sq) use ($single) {
                                $sq->where('name', 'like', "%{$single}%");
                            })
                            // 発注者（作成者）名
                            ->orWhereHas('requester', function ($uq) use ($single) {
                                $uq->where('name', 'like', "%{$single}%");
                            })
                            // アイテムの品名／メーカー名（資材マスタ）
                            ->orWhereHas('items.material', function ($mq) use ($single) {
                                $mq->where(function ($mm) use ($single) {
                                    $mm->where('name', 'like', "%{$single}%")
                                        ->orWhere('manufacturer_name', 'like', "%{$single}%");
                                });
                            })
                            // 発注アイテムのスキャン用トークン（先頭一致／全一致どちらも可）
                            ->orWhereHas('items', function ($iq) use ($single) {
                                $iq->where(function ($iqq) use ($single) {
                                    $iqq->where('scan_token', $single)
                                        ->orWhere('scan_token', 'like', $single.'%');
                                });
                            })
                            // 単発（アドホック）品目の説明
                            ->orWhereHas('items', function ($iq) use ($single) {
                                $iq->where('description', 'like', "%{$single}%");
                            });
                    });
                }
            })
            ->when($status !== '', fn ($qrb) => $qrb->where('status', $status))
            ->latest('id')
            ->paginate(50);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPoNumber(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatedRequesterId(): void
    {
        $this->resetPage();
    }

    public function updatedIssueDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedIssueDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedExpectedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedExpectedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = '';
        $this->poNumber = '';
        $this->supplierId = '';
        $this->requesterId = '';
        $this->issueDate = [
            'start' => null,
            'end' => null,
        ];
        $this->expectedDate = [
            'start' => null,
            'end' => null,
        ];
        $this->receivingDate = [
            'start' => null,
            'end' => null,
        ];
        $this->resetPage();
    }

    public function openExportModal(): void
    {
        $this->resetErrorBag('receivingDate');
        $this->resetErrorBag('aggregateType');
        // defaults
        if (! in_array($this->aggregateType, ['amount', 'quantity'], true)) {
            $this->aggregateType = 'amount';
        }
        $this->showExportModal = true;
    }

    /**
     * Active option groups for select inputs in export modal.
     */
    public function getOptionGroupsProperty()
    {
        return OptionGroup::query()->active()->ordered()->get(['id', 'name']);
    }

    /**
     * Excel export for order & delivery history filtered by receiving date range.
     */
    public function exportExcel(): ?StreamedResponse
    {
        $from = (string) ($this->receivingDate['start'] ?? '');
        $to = (string) ($this->receivingDate['end'] ?? '');

        if ($from === '' || $to === '') {
            $this->addError('receivingDate', __('procflow::po.export.validation.receiving_required'));

            return null;
        }

        if (! in_array($this->aggregateType, ['amount', 'quantity'], true)) {
            $this->addError('aggregateType', __('procflow::po.export.validation.aggregate_required'));

            return null;
        }

        // Build dataset
        $items = ReceivingItem::query()
            ->join((new Receiving)->getTable().' as r', 'r.id', '=', (new ReceivingItem)->getTable().'.receiving_id')
            ->whereDate('r.received_at', '>=', $from)
            ->whereDate('r.received_at', '<=', $to)
            ->with([
                'receiving:id,purchase_order_id,received_at,notes',
                // include manufacturer on item for ad-hoc lines
                'purchaseOrderItem:id,purchase_order_id,material_id,qty_ordered,price_unit,note,description,manufacturer',
                'purchaseOrderItem.purchaseOrder:id,po_number,supplier_id,issue_date',
                // include manufacturer_name on material when available
                'purchaseOrderItem.material:id,sku,name,manufacturer_name',
                'purchaseOrderItem.purchaseOrder.supplier:id,name',
                // Options (if any)
                'purchaseOrderItem.optionValues.option:id,name',
                'purchaseOrderItem.optionValues.group:id,name,sort_order',
            ])
            ->orderBy('r.received_at', 'asc')
            ->select((new ReceivingItem)->getTable().'.*', 'r.received_at')
            ->get();

        // Spreadsheet
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        // Build dynamic option group headers based on data in range
        $baseBeforeOptionHeaders = [
            __('procflow::po.export.excel.headers.order_no'),
            __('procflow::po.export.excel.headers.supplier'),
            __('procflow::po.export.excel.headers.issue_date'),
            __('procflow::po.export.excel.headers.received_at'),
            __('procflow::po.export.excel.headers.sku'),
            __('procflow::po.export.excel.headers.name'),
            __('procflow::po.export.excel.headers.manufacturer'),
        ];
        $baseAfterOptionHeaders = [
            __('procflow::po.export.excel.headers.qty_ordered'),
            __('procflow::po.export.excel.headers.qty_received'),
            __('procflow::po.export.excel.headers.unit_price'),
            __('procflow::po.export.excel.headers.amount'),
            __('procflow::po.export.excel.headers.note'),
        ];

        // Collect unique option groups used in the result set
        /** @var array<int, array{id:int,name:string,sort:int}> $groupMeta */
        $groupMeta = [];
        foreach ($items as $riForGroups) {
            $poiForGroups = $riForGroups->purchaseOrderItem;
            $ovc = $poiForGroups?->optionValues;
            if (! $ovc) {
                continue;
            }
            foreach ($ovc as $ov) {
                $gid = (int) ($ov->group?->id ?? 0);
                if ($gid > 0 && ! isset($groupMeta[$gid])) {
                    $groupMeta[$gid] = [
                        'id' => $gid,
                        'name' => (string) ($ov->group?->name ?? ''),
                        'sort' => (int) ($ov->group?->sort_order ?? 0),
                    ];
                }
            }
        }

        // Sort groups by sort_order then name
        $sortedGroups = array_values($groupMeta);
        usort($sortedGroups, static function ($a, $b) {
            if ($a['sort'] === $b['sort']) {
                return strcmp($a['name'], $b['name']);
            }

            return $a['sort'] <=> $b['sort'];
        });

        $dynamicOptionHeaders = array_map(static fn ($g) => $g['name'], $sortedGroups);

        $headers = array_merge($baseBeforeOptionHeaders, $dynamicOptionHeaders, $baseAfterOptionHeaders);
        $rows = [$headers];

        foreach ($items as $ri) {
            /** @var ReceivingItem $ri */
            $poi = $ri->purchaseOrderItem;
            $po = $poi?->purchaseOrder;
            $rcv = $ri->receiving;
            $mat = $poi?->material;
            $poNumber = (string) ($po?->po_number ?? '');
            $supplierName = (string) ($po?->supplier?->name ?? '');
            $issueDate = $po?->issue_date ? Carbon::parse($po->issue_date)->format('Y-m-d') : '';
            $receivedAt = $rcv?->received_at ? Carbon::parse($rcv->received_at)->format('Y-m-d') : '';
            $sku = (string) ($mat?->sku ?? '');
            $name = (string) ($mat?->name ?? ($poi?->description ?? ''));
            $manufacturer = (string) ($mat?->manufacturer_name ?? ($poi?->manufacturer ?? ''));
            $qtyOrdered = (float) ($poi?->qty_ordered ?? 0);
            $qtyReceived = (float) ($ri->qty_received ?? 0);
            $unitPrice = (float) ($poi?->price_unit ?? 0);
            $amount = $unitPrice * $qtyReceived;
            $note = (string) ($poi?->note ?? '');

            // Map option values to dynamic group columns (option name per group)
            $optionValuesByGroupId = [];
            /** @var \Illuminate\Support\Collection<int, \Lastdino\ProcurementFlow\Models\PurchaseOrderItemOptionValue>|null $ovc */
            $ovc = $poi?->optionValues;
            if ($ovc) {
                foreach ($ovc as $ov) {
                    $gid = (int) ($ov->group?->id ?? 0);
                    if ($gid > 0) {
                        $optionValuesByGroupId[$gid] = (string) ($ov->option?->name ?? '');
                    }
                }
            }

            // Compose row: base-before, dynamic option group columns, base-after
            $dynamicOptionValues = [];
            foreach ($sortedGroups as $g) {
                $dynamicOptionValues[] = (string) ($optionValuesByGroupId[$g['id']] ?? '');
            }

            $values = array_merge(
                [$poNumber, $supplierName, $issueDate, $receivedAt, $sku, $name, $manufacturer],
                $dynamicOptionValues,
                [(float) $qtyOrdered, (float) $qtyReceived, (float) $unitPrice, (float) $amount, $note]
            );
            $rows[] = $values;
        }

        // Dump all rows starting at A1
        $sheet->fromArray($rows, null, 'A1', true);

        // Auto size columns
        foreach (range(1, count($headers)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        // ==============================
        // Matrix summary sheet (by options)
        // ==============================
        // Determine axis groups (allow user selection; else: prefer 費用区分/部門区分; else first/second detected)
        $rowGroup = null; // ['id'=>int,'name'=>string]
        $colGroup = null; // ['id'=>int,'name'=>string]
        $groupsById = [];
        foreach ($sortedGroups as $g) {
            $groupsById[(int) $g['id']] = $g;
        }
        $userSelectedRow = $this->rowGroupId !== null && $this->rowGroupId !== 0;
        $userSelectedCol = $this->colGroupId !== null && $this->colGroupId !== 0;

        if ($userSelectedRow) {
            $rid = (int) $this->rowGroupId;
            if (isset($groupsById[$rid])) {
                $rowGroup = $groupsById[$rid];
            }
        }
        if ($userSelectedCol) {
            $cid = (int) $this->colGroupId;
            if (isset($groupsById[$cid])) {
                $colGroup = $groupsById[$cid];
            }
        }
        // If neither axis selected by user, auto-detect preferred axes
        if (! $userSelectedRow && ! $userSelectedCol && $rowGroup === null) {
            foreach ($sortedGroups as $g) {
                if ($g['name'] === '費用区分') {
                    $rowGroup = $g;
                    break;
                }
            }
        }
        if (! $userSelectedRow && ! $userSelectedCol && $colGroup === null) {
            foreach ($sortedGroups as $g) {
                if ($g['name'] === '部門区分') {
                    $colGroup = $g;
                    break;
                }
            }
        }
        if (! $userSelectedRow && $rowGroup === null && ! empty($sortedGroups)) {
            $rowGroup = $sortedGroups[0];
        }
        // If user selected only row group, keep single-axis (do not auto-fill column)
        if (! $userSelectedRow && $colGroup === null) {
            foreach ($sortedGroups as $g) {
                if (! $rowGroup || $g['id'] !== $rowGroup['id']) {
                    $colGroup = $g;
                    break;
                }
            }
        }
        // If user selected only column group and row is still null, try to auto-pick a different row group
        if ($userSelectedCol && ! $userSelectedRow && $rowGroup === null) {
            foreach ($sortedGroups as $g) {
                if ($g['id'] !== $colGroup['id']) {
                    $rowGroup = $g;
                    break;
                }
            }
            // Fallback: if nothing else, allow same group as row
            if ($rowGroup === null && $colGroup !== null) {
                $rowGroup = $colGroup;
            }
        }

        // Only create a matrix sheet when at least one axis exists
        if ($rowGroup !== null) {
            $rowLabel = (string) $rowGroup['name'];
            $colLabel = (string) ($colGroup['name'] ?? '');

            // Collect distinct option values for axis groups from the dataset
            $rowKeys = [];
            $colKeys = [];
            foreach ($items as $ri0) {
                $poi0 = $ri0->purchaseOrderItem;
                $ovc0 = $poi0?->optionValues;
                $rowKey = __('procflow::po.export.excel.matrix.unset');
                $colKey = __('procflow::po.export.excel.matrix.unset');
                if ($ovc0) {
                    foreach ($ovc0 as $ov0) {
                        $gid0 = (int) ($ov0->group?->id ?? 0);
                        if ($rowGroup && $gid0 === (int) $rowGroup['id']) {
                            $rowKey = (string) ($ov0->option?->name ?? __('procflow::po.export.excel.matrix.unset'));
                        }
                        if ($colGroup && $gid0 === (int) $colGroup['id']) {
                            $colKey = (string) ($ov0->option?->name ?? __('procflow::po.export.excel.matrix.unset'));
                        }
                    }
                }
                $rowKeys[$rowKey] = true;
                if ($colGroup !== null) {
                    $colKeys[$colKey] = true;
                }
            }

            // Fallback for when there is no column group: use single column unset label
            if ($colGroup === null) {
                $colKeys = [__('procflow::po.export.excel.matrix.unset') => true];
            }

            $rowValues = array_keys($rowKeys);
            sort($rowValues, SORT_NATURAL);
            $colValues = array_keys($colKeys);
            sort($colValues, SORT_NATURAL);

            // Initialize matrix sums
            $matrix = [];
            foreach ($rowValues as $rk) {
                $matrix[$rk] = [];
                foreach ($colValues as $ck) {
                    $matrix[$rk][$ck] = 0.0;
                }
            }

            // Aggregate amounts (qty_received * unit_price)
            foreach ($items as $ri1) {
                $poi1 = $ri1->purchaseOrderItem;
                $ovc1 = $poi1?->optionValues;
                $rowKey = __('procflow::po.export.excel.matrix.unset');
                $colKey = __('procflow::po.export.excel.matrix.unset');
                if ($ovc1) {
                    foreach ($ovc1 as $ov1) {
                        $gid1 = (int) ($ov1->group?->id ?? 0);
                        if ($rowGroup && $gid1 === (int) $rowGroup['id']) {
                            $rowKey = (string) ($ov1->option?->name ?? __('procflow::po.export.excel.matrix.unset'));
                        }
                        if ($colGroup && $gid1 === (int) $colGroup['id']) {
                            $colKey = (string) ($ov1->option?->name ?? __('procflow::po.export.excel.matrix.unset'));
                        }
                    }
                }
                $qty1 = (float) ($ri1->qty_received ?? 0);
                $amount1 = (float) (($poi1?->price_unit ?? 0) * $qty1);
                if (! isset($matrix[$rowKey])) {
                    $matrix[$rowKey] = [];
                }
                if (! isset($matrix[$rowKey][$colKey])) {
                    $matrix[$rowKey][$colKey] = 0.0;
                }
                $matrix[$rowKey][$colKey] += ($this->aggregateType === 'quantity') ? $qty1 : $amount1;
            }

            // Build sheet rows
            $matrixHeaders = array_merge([''], $colValues, ['計']);
            $matrixRows = [$matrixHeaders];
            $columnTotals = array_fill_keys($colValues, 0.0);
            $grandTotal = 0.0;
            foreach ($rowValues as $rk) {
                $rowTotal = 0.0;
                $dataRow = [$rk];
                foreach ($colValues as $ck) {
                    $val = (float) ($matrix[$rk][$ck] ?? 0.0);
                    $dataRow[] = $val;
                    $rowTotal += $val;
                    $columnTotals[$ck] += $val;
                }
                $grandTotal += $rowTotal;
                $dataRow[] = $rowTotal;
                $matrixRows[] = $dataRow;
            }
            // Final totals row
            $totalsRow = [__('procflow::po.export.excel.matrix.total')];
            foreach ($colValues as $ck) {
                $totalsRow[] = (float) $columnTotals[$ck];
            }
            $totalsRow[] = (float) $grandTotal;
            $matrixRows[] = $totalsRow;

            // Create new sheet and dump
            $matrixSheet = $spreadsheet->createSheet();
            $matrixSheet->setTitle(__('procflow::po.export.excel.matrix.sheet_title'));
            $matrixSheet->fromArray($matrixRows, null, 'A1', true);
            // Auto-size
            foreach (range(1, count($matrixHeaders)) as $colIndex) {
                $matrixSheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
            }
        }

        $today = Carbon::now()->format('Ymd');
        $filename = "注文納品履歴_{$today}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename);
    }

    // Modal helpers
    public function openCreatePo(): void
    {
        $this->resetPoForm();
        $this->showPoModal = true;
    }

    public function closeCreatePo(): void
    {
        $this->showPoModal = false;
    }

    public function addPoItem(): void
    {
        $this->poForm['items'][] = ['material_id' => '', 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => null, 'tax_locked' => false, 'description' => null, 'desired_date' => null, 'expected_date' => null, 'note' => null, 'options' => []];
    }

    public function removePoItem(int $index): void
    {
        unset($this->poForm['items'][$index]);
        $this->poForm['items'] = array_values($this->poForm['items']);
    }

    public function getSuppliersProperty()
    {
        return \Lastdino\ProcurementFlow\Models\Supplier::query()
            ->orderBy('name')
            ->get(['id', 'name', 'auto_send_po']);
    }

    public function getMaterialsProperty()
    {
        return \Lastdino\ProcurementFlow\Models\Material::query()
            ->active()
            ->orderBy('sku')
            ->get();
    }

    public function getUsersProperty()
    {
        return \App\Models\User::query()
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name']);
    }

    // Active option groups and options for Create PO modal (UI auto-reflection)
    public function getActiveGroupsProperty()
    {
        return OptionGroup::query()->active()->ordered()->get(['id', 'name']);
    }

    /**
     * @return array<int, array<int, array{id:int,name:string}>>
     */
    public function getActiveOptionsByGroupProperty(): array
    {
        $options = Option::query()->active()->ordered()->get(['id', 'name', 'group_id']);
        $by = [];
        foreach ($options as $opt) {
            $gid = (int) $opt->getAttribute('group_id');
            $by[$gid][] = [
                'id' => (int) $opt->getKey(),
                'name' => (string) $opt->getAttribute('name'),
            ];
        }

        return $by;
    }

    /**
     * Preview grouping of current form items by supplier based on each material's preferred supplier.
     *
     * @return array<int, array{supplier_id:int,name:string,lines:int,subtotal:float}>
     */
    public function getPoSupplierPreviewProperty(): array
    {
        $preview = [];
        $groups = [];
        foreach (array_values($this->poForm['items']) as $idx => $line) {
            $materialId = $line['material_id'] ?? null;
            if (empty($materialId)) {
                // skip ad-hoc in this flow
                continue;
            }
            /** @var \Lastdino\ProcurementFlow\Models\Material|null $mat */
            $mat = \Lastdino\ProcurementFlow\Models\Material::find((int) $materialId);
            if (! $mat || is_null($mat->preferred_supplier_id)) {
                continue;
            }
            $sid = (int) $mat->preferred_supplier_id;
            if (! isset($groups[$sid])) {
                /** @var \Lastdino\ProcurementFlow\Models\Supplier|null $sup */
                $sup = \Lastdino\ProcurementFlow\Models\Supplier::query()->find($sid);
                $groups[$sid] = [
                    'supplier_id' => $sid,
                    'name' => $sup?->name ?? ('Supplier #'.$sid),
                    'lines' => 0,
                    'subtotal' => 0.0,
                ];
            }
            $qty = (float) ($line['qty_ordered'] ?? 0);
            $price = (float) ($line['price_unit'] ?? 0);
            $groups[$sid]['lines'] += 1;
            $groups[$sid]['subtotal'] += ($qty * $price);
        }
        // normalize
        foreach ($groups as $g) {
            $g['subtotal'] = (float) $g['subtotal'];
            $preview[] = $g;
        }

        return $preview;
    }

    public function savePoFromModal(): void
    {
        // 承認フロー事前チェック（validateの前で止める）
        if (! $this->ensureApprovalFlowConfigured()) {
            return;
        }

        $rules = (new \Lastdino\ProcurementFlow\Http\Requests\StorePurchaseOrderRequest)->rules();

        // Normalize items payload to ensure keys existence (especially options)
        $items = array_map(function ($line) {
            return [
                'material_id' => $line['material_id'] ?? null,
                'description' => $line['description'] ?? null,
                'unit_purchase' => $line['unit_purchase'] ?? '',
                'qty_ordered' => $line['qty_ordered'] ?? null,
                'price_unit' => $line['price_unit'] ?? null,
                'tax_rate' => $line['tax_rate'] ?? null,
                'desired_date' => $line['desired_date'] ?? null,
                'expected_date' => $line['expected_date'] ?? null,
                'note' => $line['note'] ?? null,
                'options' => (array) ($line['options'] ?? []),
            ];
        }, array_values($this->poForm['items']));

        // Validate with "poForm."-prefixed keys so error bag aligns with wire:model / <flux:error name="poForm.*"> in Blade
        $payload = [
            'poForm' => [
                'supplier_id' => $this->poForm['supplier_id'],
                'expected_date' => $this->poForm['expected_date'],
                'delivery_location' => (string) ($this->poForm['delivery_location'] ?? ''),
                'items' => $items,
            ],
        ];
        $validated = $this->validatePurchaseOrderPayload('poForm', $payload, $rules);

        // Path A: legacy/single-supplier explicit flow when supplier_id provided
        if (! empty($validated['supplier_id'])) {
            // Build input for shared factory
            [$optionService, $factory, $approval] = $this->services();

            $lines = [];
            foreach ($validated['items'] as $idx => $line) {
                $lines[] = [
                    'material_id' => (isset($line['material_id']) && $line['material_id'] !== '' && $line['material_id'] !== null)
                        ? (int) $line['material_id']
                        : null,
                    'description' => $line['description'] ?? null,
                    'unit_purchase' => (string) $line['unit_purchase'],
                    'qty_ordered' => (float) $line['qty_ordered'],
                    'price_unit' => (float) $line['price_unit'],
                    'tax_rate' => $line['tax_rate'] ?? null,
                    'desired_date' => $line['desired_date'] ?? null,
                    'expected_date' => $line['expected_date'] ?? null,
                    'note' => $line['note'] ?? null,
                    // Normalize & validate options with shared service
                    'options' => $optionService->normalizeAndValidate((array) ($this->poForm['items'][$idx]['options'] ?? [])),
                ];
            }

            $poInput = [
                'supplier_id' => (int) $validated['supplier_id'],
                'expected_date' => $validated['expected_date'] ?? null,
                'delivery_location' => (string) ($validated['delivery_location'] ?? ''),
                'items' => $lines,
            ];

            /** @var \Lastdino\ProcurementFlow\Models\PurchaseOrder $po */
            $po = $factory->create($poInput, true);
            // 承認フロー登録（設定されたFlow IDで登録）
            $approval->registerForPo($po);

            $this->showPoModal = false;
            $this->redirectToPoShow($po);

            return;
        }

        // Path B: supplier-less flow → split by each material's preferred supplier
        $items = array_values($validated['items']);
        $groups = [];
        foreach ($items as $idx => $line) {
            $matId = $line['material_id'] ?? null;
            if (is_null($matId)) {
                // Should be prevented by validator for this flow
                $this->addError('poForm.items.'.$idx.'.material_id', 'アドホック行はこのフローでは使用できません。');

                return;
            }
            /** @var \Lastdino\ProcurementFlow\Models\Material|null $mat */
            $mat = \Lastdino\ProcurementFlow\Models\Material::find((int) $matId);
            if (! $mat || is_null($mat->preferred_supplier_id)) {
                $this->addError('poForm.items.'.$idx.'.material_id', 'この資材に紐づくサプライヤーが未設定のため、自動発注できません。');

                return;
            }
            $sid = (int) $mat->preferred_supplier_id;
            $groups[$sid][] = ['idx' => $idx, 'line' => $line];
        }

        $optionService = app(\Lastdino\ProcurementFlow\Services\OptionSelectionService::class);
        $factory = app(\Lastdino\ProcurementFlow\Services\PurchaseOrderFactory::class);
        $approval = app(\Lastdino\ProcurementFlow\Services\ApprovalFlowRegistrar::class);

        $created = [];
        foreach ($groups as $sid => $lines) {
            $compiledLines = [];
            foreach ($lines as $entry) {
                $idx = (int) $entry['idx'];
                $line = $entry['line'];
                $compiledLines[] = [
                    'material_id' => $line['material_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'unit_purchase' => (string) $line['unit_purchase'],
                    'qty_ordered' => (float) $line['qty_ordered'],
                    'price_unit' => (float) $line['price_unit'],
                    'tax_rate' => $line['tax_rate'] ?? null,
                    'desired_date' => $line['desired_date'] ?? null,
                    'expected_date' => $line['expected_date'] ?? null,
                    'note' => $line['note'] ?? null,
                    'options' => $optionService->normalizeAndValidate((array) ($this->poForm['items'][$idx]['options'] ?? [])),
                ];
            }

            $poInput = [
                'supplier_id' => (int) $sid,
                'expected_date' => $validated['expected_date'] ?? null,
                'delivery_location' => (string) ($validated['delivery_location'] ?? ''),
                'items' => $compiledLines,
            ];

            /** @var \Lastdino\ProcurementFlow\Models\PurchaseOrder $po */
            $po = $factory->create($poInput, true);
            $approval->registerForPo($po);
            $created[] = $po->id;
        }

        $this->showPoModal = false;
        $count = count($created);
        $this->dispatch('toast', type: 'success', message: $count.'件の発注書を作成しました');
        // Stay on index; optionally we could redirect when only one created.
    }

    // When supplier changes, prefill auto_send flag from supplier default
    public function updatedPoFormSupplierId($value): void
    {
        $supplierId = (int) ($value ?? 0);
        if ($supplierId <= 0) {
            return;
        }

        /** @var \Lastdino\ProcurementFlow\Models\Supplier|null $sup */
        $sup = \Lastdino\ProcurementFlow\Models\Supplier::query()->find($supplierId);
        if ($sup) {
            // UI では選択しない方針のため、何もしない
        }
    }

    // Ad-hoc order helpers
    public function openAdhocPo(): void
    {
        $this->resetAdhocForm();
        // Prefill initial ad-hoc line tax_rate from config default (schedule-aware)
        $expectedDate = isset($this->adhocForm['expected_date']) && $this->adhocForm['expected_date'] ? \Carbon\Carbon::parse($this->adhocForm['expected_date']) : null;
        $taxSet = $this->resolveCurrentItemTaxSet($expectedDate);
        if (isset($this->adhocForm['items'][0]) && (is_null($this->adhocForm['items'][0]['tax_rate']) || $this->adhocForm['items'][0]['tax_rate'] === '')) {
            $this->adhocForm['items'][0]['tax_rate'] = (float) ($taxSet['default_rate'] ?? 0.10);
        }
        $this->showAdhocPoModal = true;
    }

    public function closeAdhocPo(): void
    {
        $this->showAdhocPoModal = false;
    }

    public function addAdhocItem(): void
    {
        // Prefill default item tax for ad-hoc lines based on expected_date and config
        $expectedDate = isset($this->adhocForm['expected_date']) && $this->adhocForm['expected_date'] ? \Carbon\Carbon::parse($this->adhocForm['expected_date']) : null;
        $taxSet = $this->resolveCurrentItemTaxSet($expectedDate);
        $defaultRate = (float) ($taxSet['default_rate'] ?? 0.10);
        $this->adhocForm['items'][] = ['description' => null, 'manufacturer' => null, 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => $defaultRate, 'tax_locked' => false, 'desired_date' => null, 'expected_date' => null, 'note' => null, 'options' => []];
    }

    public function removeAdhocItem(int $index): void
    {
        unset($this->adhocForm['items'][$index]);
        $this->adhocForm['items'] = array_values($this->adhocForm['items']);
    }

    public function saveAdhocPoFromModal(): void
    {
        // 承認フロー事前チェック（validateの前で止める）
        if (! $this->ensureApprovalFlowConfigured()) {
            return;
        }

        // Reuse same rules; items will have material_id null and require description
        $rules = (new \Lastdino\ProcurementFlow\Http\Requests\StorePurchaseOrderRequest)->rules();

        // Map adhoc items to expected structure (material_id => null)
        $items = array_map(function ($line) {
            return [
                'material_id' => null,
                'description' => $line['description'] ?? null,
                'manufacturer' => $line['manufacturer'] ?? null,
                'unit_purchase' => $line['unit_purchase'] ?? '',
                'qty_ordered' => $line['qty_ordered'] ?? null,
                'price_unit' => $line['price_unit'] ?? null,
                'tax_rate' => $line['tax_rate'] ?? null,
                'desired_date' => $line['desired_date'] ?? null,
                'expected_date' => $line['expected_date'] ?? null,
                'note' => $line['note'] ?? null,
                // pass through options for validation (required per active group)
                'options' => (array) ($line['options'] ?? []),
            ];
        }, array_values($this->adhocForm['items']));

        // Validate with "adhocForm."-prefixed keys so error bag aligns with wire:model (error bag name should match your Blade bindings)
        $payload = [
            'adhocForm' => [
                'supplier_id' => $this->adhocForm['supplier_id'],
                'expected_date' => $this->adhocForm['expected_date'],
                'delivery_location' => (string) ($this->adhocForm['delivery_location'] ?? ''),
                'items' => $items,
            ],
        ];
        $validated = $this->validatePurchaseOrderPayload('adhocForm', $payload, $rules);

        // アドホック発注フローでは supplier_id は必須（FormRequest の withValidator は使っていないため、ここで強制）
        if (empty($validated['supplier_id'])) {
            $this->addError('adhocForm.supplier_id', 'アドホック行が含まれるため、サプライヤーの選択が必要です。');

            return;
        }

        // Build lines and create via shared factory/services
        [$optionService, $factory, $approval] = $this->services();

        $lines = [];
        foreach ($validated['items'] as $idx => $line) {
            $lines[] = [
                'material_id' => null,
                'description' => $line['description'] ?? null,
                'manufacturer' => $line['manufacturer'] ?? null,
                'unit_purchase' => (string) $line['unit_purchase'],
                'qty_ordered' => (float) $line['qty_ordered'],
                'price_unit' => (float) $line['price_unit'],
                'tax_rate' => $line['tax_rate'] ?? null, // 指定があれば優先、なければ Factory が既定税率を解決
                'desired_date' => $line['desired_date'] ?? null,
                'expected_date' => $line['expected_date'] ?? null,
                'note' => $line['note'] ?? null,
                'options' => $optionService->normalizeAndValidate((array) ($this->adhocForm['items'][$idx]['options'] ?? [])),
            ];
        }

        $poInput = [
            'supplier_id' => (int) $validated['supplier_id'],
            'expected_date' => $validated['expected_date'] ?? null,
            'delivery_location' => (string) ($validated['delivery_location'] ?? ''),
            'items' => $lines,
        ];

        /** @var \Lastdino\ProcurementFlow\Models\PurchaseOrder $po */
        $po = $factory->create($poInput, true);
        $approval->registerForPo($po);

        $this->showAdhocPoModal = false;
        $this->redirectToPoShow($po);
    }

    /**
     * 現在（または予定日）に有効な商品税セットを返す。
     *
     * @return array{default_rate: float, rates: array<string,float>}
     */
    protected function resolveCurrentItemTaxSet(?\Carbon\Carbon $at): array
    {
        $cfg = (array) config('procurement-flow.item_tax', []);
        $default = (float) ($cfg['default_rate'] ?? 0.10);
        $rates = (array) ($cfg['rates'] ?? []);
        $schedule = (array) ($cfg['schedule'] ?? []);

        if ($at && ! empty($schedule)) {
            foreach ($schedule as $entry) {
                $from = $entry['effective_from'] ?? null;
                if ($from && $at->greaterThanOrEqualTo(\Carbon\Carbon::parse($from))) {
                    $default = (float) ($entry['default_rate'] ?? $default);
                    $rates = array_merge($rates, (array) ($entry['rates'] ?? []));
                }
            }
        }

        return ['default_rate' => $default, 'rates' => $rates];
    }

    /**
     * 資材の tax_code に応じて税率を返す。該当コードが無い場合はデフォルト。
     */
    protected function resolveMaterialTaxRate(?\Lastdino\ProcurementFlow\Models\Material $material, array $taxSet): float
    {
        $code = $material ? (string) ($material->getAttribute('tax_code') ?? 'standard') : 'standard';
        $default = (float) ($taxSet['default_rate'] ?? 0.10);
        $rates = (array) ($taxSet['rates'] ?? []);

        return match ($code) {
            'reduced' => (float) ($rates['reduced'] ?? $default),
            default => $default,
        };
    }

    public function onMaterialChanged(int $index, $materialId): void
    {
        $materialId = (int) $materialId;
        if (! isset($this->poForm['items'][$index])) {
            return;
        }

        if ($materialId === 0) {
            // Reset unit when material cleared
            $this->poForm['items'][$index]['unit_purchase'] = '';

            return;
        }

        /** @var \Lastdino\ProcurementFlow\Models\Material|null $material */
        $material = \Lastdino\ProcurementFlow\Models\Material::find($materialId);
        if ($material) {
            // In supplier-less flow, disallow materials without preferred supplier
            $preferred = $material->preferred_supplier_id;
            if (is_null($preferred) && empty($this->poForm['supplier_id'])) {
                $this->poForm['items'][$index]['material_id'] = null;
                $this->poForm['items'][$index]['unit_purchase'] = '';
                $this->addError("poForm.items.$index.material_id", 'この資材に紐づくサプライヤーが未設定です。資材に指定サプライヤーを設定してください。');

                return;
            }
            // If supplier is not chosen yet and material has preferred supplier, auto-assign it
            if (empty($this->poForm['supplier_id']) && ! is_null($preferred)) {
                $this->poForm['supplier_id'] = (int) $preferred;
            }
            $defaultUnit = $material->unit_purchase_default ?: $material->unit_stock;
            $this->poForm['items'][$index]['unit_purchase'] = (string) $defaultUnit;
            // Also default unit price from material master if present
            if (! is_null($material->unit_price)) {
                $this->poForm['items'][$index]['price_unit'] = (float) $material->unit_price;
            }
            // Auto-fill tax rate if not set by user
            $current = $this->poForm['items'][$index]['tax_rate'] ?? null;
            if ($current === null || $current === '') {
                $exp = isset($this->poForm['expected_date']) && $this->poForm['expected_date'] ? \Carbon\Carbon::parse($this->poForm['expected_date']) : null;
                $taxSet = $this->resolveCurrentItemTaxSet($exp);
                $this->poForm['items'][$index]['tax_rate'] = $this->resolveMaterialTaxRate($material, $taxSet);
                $this->poForm['items'][$index]['tax_locked'] = false; // mark as auto-applied
            }
        }
    }

    // When expected_date changes, re-evaluate auto-applied (null) tax rates
    public function updatedPoFormExpectedDate($value): void
    {
        $exp = ! empty($value) ? \Carbon\Carbon::parse($value) : null;
        $taxSet = $this->resolveCurrentItemTaxSet($exp);
        foreach ($this->poForm['items'] as $i => $line) {
            $current = $line['tax_rate'] ?? null;
            $locked = (bool) ($line['tax_locked'] ?? false);
            if (! $locked) {
                $materialId = $line['material_id'] ?? null;
                if (! is_null($materialId)) {
                    /** @var \Lastdino\ProcurementFlow\Models\Material|null $material */
                    $material = \Lastdino\ProcurementFlow\Models\Material::find((int) $materialId);
                    $this->poForm['items'][$i]['tax_rate'] = $this->resolveMaterialTaxRate($material, $taxSet);
                } else {
                    // Ad-hoc default
                    $this->poForm['items'][$i]['tax_rate'] = (float) ($taxSet['default_rate'] ?? 0.10);
                }
            }
        }
    }

    // Detect manual override of tax_rate and lock the line against auto-updates
    public function updatedPoForm($value, $name): void
    {
        // $name example: 'poForm.items.1.tax_rate'
        if (is_string($name) && str_ends_with($name, '.tax_rate')) {
            // extract index
            $parts = explode('.', $name);
            $idxKey = array_search('items', $parts, true);
            if ($idxKey !== false && isset($parts[$idxKey + 1])) {
                $i = (int) $parts[$idxKey + 1];
                if (isset($this->poForm['items'][$i])) {
                    $this->poForm['items'][$i]['tax_locked'] = true;
                }
            }
        }
    }

    protected function resetPoForm(): void
    {
        $this->poForm = [
            'supplier_id' => null,
            'expected_date' => null,
            // 発注単位の納品先（未指定時はPDF設定の既定値を初期値として表示）
            'delivery_location' => (string) (Settings::pdf()['delivery_location'] ?? ''),
            'items' => [
                ['material_id' => '', 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => null, 'tax_locked' => false, 'description' => null, 'desired_date' => null, 'expected_date' => null, 'note' => null, 'options' => []],
            ],
        ];
    }

    protected function resetAdhocForm(): void
    {
        $this->adhocForm = [
            'supplier_id' => null,
            'expected_date' => null,
            // 発注単位の納品先（未指定時はPDF設定の既定値を初期値として表示）
            'delivery_location' => (string) (Settings::pdf()['delivery_location'] ?? ''),
            'items' => [
                ['description' => null, 'unit_purchase' => '', 'qty_ordered' => null, 'price_unit' => null, 'tax_rate' => null, 'tax_locked' => false, 'desired_date' => null, 'expected_date' => null, 'note' => null, 'options' => []],
            ],
        ];
    }

    /**
     * 与えられたバリデーションルール配列のキー（フィールド名）にフォーム配列名のプレフィックスを付与します。
     *
     * 例: [ 'items.*.qty_ordered' => 'required' ] に対して、prefix が 'poForm.' の場合、
     *     [ 'poForm.items.*.qty_ordered' => 'required' ] を返します。
     */
    protected function prefixFormRules(array $rules, string $prefix): array
    {
        $prefixed = [];

        foreach ($rules as $key => $rule) {
            $newKey = $prefix.$key;

            // Prefix field references inside dependent validation rules (strings)
            // Only for rules where other field names are referenced (required_with/without and their variants)
            $newRule = $rule;

            $dependentRuleNames = [
                'required_with', 'required_with_all', 'required_with_any',
                'required_without', 'required_without_all', 'required_without_any',
            ];

            $prefixFieldList = function (string $list) use ($prefix): string {
                $parts = array_map('trim', explode(',', $list));
                $parts = array_map(function ($field) use ($prefix) {
                    // Avoid double-prefixing
                    if ($field === '') {
                        return $field;
                    }

                    return str_starts_with($field, $prefix) ? $field : $prefix.$field;
                }, $parts);

                return implode(',', $parts);
            };

            $transformStringRule = function (string $ruleStr) use ($dependentRuleNames, $prefixFieldList): string {
                $segments = explode('|', $ruleStr);
                foreach ($segments as &$seg) {
                    if (strpos($seg, ':') === false) {
                        continue;
                    }
                    [$name, $value] = explode(':', $seg, 2);
                    if (in_array($name, $dependentRuleNames, true)) {
                        $seg = $name.':'.$prefixFieldList($value);
                    }
                }
                unset($seg);

                return implode('|', $segments);
            };

            if (is_string($newRule)) {
                $newRule = $transformStringRule($newRule);
            } elseif (is_array($newRule)) {
                $newRule = array_map(function ($r) use ($transformStringRule) {
                    if (is_string($r)) {
                        return $transformStringRule($r);
                    }

                    // Leave Rule objects and other instances as-is
                    return $r;
                }, $newRule);
            }

            $prefixed[$newKey] = $newRule;
        }

        return $prefixed;
    }

    /**
     * 共通: 承認フローの事前チェック。未設定ならエラーを積んで false を返す。
     */
    protected function ensureApprovalFlowConfigured(): bool
    {
        try {
            $flowIdStr = \Lastdino\ProcurementFlow\Models\AppSetting::get('approval_flow.purchase_order_flow_id');
            $flowId = (int) ($flowIdStr ?? 0);
            if ($flowId <= 0 || ! \Lastdino\ApprovalFlow\Models\ApprovalFlow::query()->whereKey($flowId)->exists()) {
                $this->addError('approval_flow', '承認フローが未設定のため発注できません。管理者に連絡してください。');

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->addError('approval_flow', '承認フローが未設定のため発注できません。管理者に連絡してください。');

            return false;
        }
    }

    /**
     * 共通: フォームごとのプレフィックスでバリデーションを実行して該当フォーム配下の配列を返す。
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $rules
     * @return array<string,mixed>
     */
    protected function validatePurchaseOrderPayload(string $formKey, array $payload, array $rules): array
    {
        $prefixedRules = $this->prefixFormRules($rules, $formKey.'.');
        $validatedAll = validator($payload, $prefixedRules)->validate();

        return $validatedAll[$formKey] ?? [];
    }

    /**
     * 共通: よく使うサービスの取得。
     *
     * @return array{0:\Lastdino\ProcurementFlow\Services\OptionSelectionService,1:\Lastdino\ProcurementFlow\Services\PurchaseOrderFactory,2:\Lastdino\ProcurementFlow\Services\ApprovalFlowRegistrar}
     */
    protected function services(): array
    {
        $optionService = app(\Lastdino\ProcurementFlow\Services\OptionSelectionService::class);
        $factory = app(\Lastdino\ProcurementFlow\Services\PurchaseOrderFactory::class);
        $approval = app(\Lastdino\ProcurementFlow\Services\ApprovalFlowRegistrar::class);

        return [$optionService, $factory, $approval];
    }

    /**
     * 共通: 作成した PO の詳細へ遷移（ルートが無い場合は何もしない）
     */
    protected function redirectToPoShow(\Lastdino\ProcurementFlow\Models\PurchaseOrder $po): void
    {
        if (\Illuminate\Support\Facades\Route::has('procurement.purchase-orders.show')) {
            $this->redirectRoute('procurement.purchase-orders.show', ['po' => $po->id]);

            return;
        }
        if (\Illuminate\Support\Facades\Route::has('purchase-orders.show')) {
            $this->redirectRoute('purchase-orders.show', ['purchase_order' => $po->id]);
        }
    }
};

?>

<div class="p-6 space-y-6">
    <x-procflow::topmenu />
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-40">
            <flux:input wire:model.live.debounce.300ms="poNumber" placeholder="{{ __('procflow::po.filters.po_number_placeholder') }}" />
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="supplierId">
                <option value="">{{ __('procflow::po.filters.supplier') }}</option>
                @foreach($this->suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="requesterId">
                <option value="">{{ __('procflow::po.filters.requester') }}</option>
                @foreach($this->users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <flux:field>
            <flux:label>{{ __('procflow::po.filters.issue_from_to') }}</flux:label>
            <div class="flex items-center gap-2">
                <flux:input type="date" wire:model.live="issueDate.start" placeholder="{{ __('procflow::po.filters.issue_from') }}" />
                <flux:input type="date" wire:model.live="issueDate.end" placeholder="{{ __('procflow::po.filters.issue_to') }}" />
            </div>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('procflow::po.filters.expected_from_to') }}</flux:label>
            <div class="flex items-center gap-2">
                <flux:input type="date" wire:model.live="expectedDate.start" placeholder="{{ __('procflow::po.filters.expected_from') }}" />
                <flux:input type="date" wire:model.live="expectedDate.end" placeholder="{{ __('procflow::po.filters.expected_to') }}" />
            </div>
        </flux:field>

        <div class="w-full sm:flex-1 min-w-64">
            <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('procflow::po.filters.search_placeholder') }}" />
        </div>
        <div class="w-full sm:w-40">
            <flux:select wire:model.live="status">
                <option value="">{{ __('procflow::po.filters.status_all') }}</option>
                <option value="draft">{{ __('procflow::po.status.draft') }}</option>
                <option value="issued">{{ __('procflow::po.status.issued') }}</option>
                <option value="receiving">{{ __('procflow::po.status.receiving') }}</option>
                <option value="closed">{{ __('procflow::po.status.closed') }}</option>
                <option value="canceled">{{ __('procflow::po.status.canceled') }}</option>
            </flux:select>
        </div>
        <div class="ms-auto flex gap-2 w-full sm:w-auto">
            <flux:button variant="outline" wire:click="clearFilters">{{ __('procflow::po.buttons.clear_filters') }}</flux:button>
            <flux:button variant="primary" wire:click="openCreatePo">{{ __('procflow::po.buttons.new_po') }}</flux:button>
            <flux:button variant="outline" wire:click="openAdhocPo">{{ __('procflow::po.buttons.adhoc_order') }}</flux:button>
            <flux:button variant="outline" wire:click="openExportModal">{{ __('procflow::po.export.history_button') }}</flux:button>
        </div>
    </div>

    <div class="rounded-lg border overflow-x-auto bg-white dark:bg-neutral-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('procflow::po.table.po_number') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::po.table.supplier') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::po.table.requester') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::po.table.status') }}</flux:table.column>
                <flux:table.column>{{ __('procflow::po.table.total') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->orders as $po)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link
                               href="{{ route('procurement.purchase-orders.show', ['po' => $po->id]) }}">
                                {{ $po->po_number ?? __('procflow::po.labels.draft_with_id', ['id' => $po->id]) }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $po->supplier->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $po->requester->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @php $status = is_string($po->status) ? $po->status : ($po->status->value ?? 'draft'); @endphp
                            @php
                                $color = match ($status) {
                                    'closed' => 'green',
                                    'issued' => 'yellow',
                                    'receiving' => 'cyan',
                                    'canceled' => 'red',
                                    default  => 'zinc',
                                };
                            @endphp
                            <flux:badge color="{{ $color }}" size="sm">{{ __('procflow::po.status.' . $status) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ \Lastdino\ProcurementFlow\Support\Format::moneyTotal($po->total) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-neutral-500 py-6">{{ __('procflow::po.table.empty') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- 履歴ダウンロードモーダル -->
    <flux:modal wire:model.self="showExportModal" name="export-history" class="w-full md:w-[40rem] max-w-full">
        <x-slot name="title">{{ __('procflow::po.export.modal.title') }}</x-slot>

        <div class="space-y-4">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">{{ __('procflow::po.export.modal.description') }}</p>

            <flux:field>
                <flux:label>{{ __('procflow::po.export.fields.receiving_from_to') }}</flux:label>
                <div class="flex items-center gap-2">
                    <div class="w-full">
                        <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="receivingDate.start" placeholder="{{ __('procflow::po.export.fields.receiving_from') }}">
                    </div>
                    <div class="w-full">
                        <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="receivingDate.end" placeholder="{{ __('procflow::po.export.fields.receiving_to') }}">
                    </div>
                </div>
                @error('receivingDate')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('procflow::po.export.fields.row_group') }}</flux:label>
                    <flux:select wire:model.live="rowGroupId">
                        <option value="">{{ __('procflow::po.export.fields.row_auto') }}</option>
                        @foreach($this->optionGroups as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('procflow::po.export.fields.col_group') }}</flux:label>
                    <flux:select wire:model.live="colGroupId">
                        <option value="">{{ __('procflow::po.export.fields.col_none') }}</option>
                        @foreach($this->optionGroups as $grp)
                            <option value="{{ $grp->id }}">{{ $grp->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('procflow::po.export.fields.aggregate_type') }}</flux:label>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" value="amount" class="accent-blue-600" wire:model.live="aggregateType">
                        {{ __('procflow::po.export.aggregate.amount') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" value="quantity" class="accent-blue-600" wire:model.live="aggregateType">
                        {{ __('procflow::po.export.aggregate.quantity') }}
                    </label>
                </div>
                @error('aggregateType')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror
            </flux:field>
        </div>
        <div class="flex items-center justify-end gap-2 mt-4">
            <flux:button variant="ghost" wire:click="$set('showExportModal', false)">{{ __('procflow::po.buttons.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="exportExcel" wire:target="exportExcel" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="exportExcel">{{ __('procflow::po.export.buttons.download') }}</span>
                <span wire:loading wire:target="exportExcel">{{ __('procflow::po.export.buttons.creating') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    <div class="flex justify-end">
        {{ $this->orders->links() }}
    </div>

    {{-- Create PO Modal (Flux UI) --}}
    <flux:modal wire:model.self="showPoModal" name="create-po" class="w-full md:w-[64rem] max-w-full" :dismissible="false">
        <div class="w-full md:w-[64rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::po.create.title') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.create.mode.title') }}</label>
                    <div class="text-sm text-neutral-700 dark:text-neutral-300">{{ __('procflow::po.create.mode.material_based') }}</div>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.expected_date') }}</label>
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="poForm.expected_date">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.requester') }}</label>
                    <input type="text" class="w-full border rounded p-2 bg-neutral-100 dark:bg-neutral-800" value="{{ auth()->user()->name ?? '—' }}" disabled>
                </div>
            </div>
            <flux:textarea
                label="{{ __('procflow::po.create.delivery.label') }}"
                placeholder="{{ __('procflow::po.create.delivery.placeholder') }}"
                wire:model.live="poForm.delivery_location"
            />

            {{-- 自動送信可否はサプライヤー設定に基づいて決定するため、UIでの選択肢は廃止 --}}

            <div class="space-y-3 mt-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-neutral-600">{{ __('procflow::po.create.items.title') }}</div>
                    <flux:button size="sm" variant="outline" wire:click="addPoItem">{{ __('procflow::po.create.items.add_row') }}</flux:button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.material') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.description') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.note') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.unit') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.unit_price') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.tax_rate') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.desired_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.expected_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.create.table.options') }}</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($poForm['items'] as $i => $row)
                                <tr class="border-t">
                                    <td class="py-2 px-3">
                                        <flux:select class="min-w-48" placeholder="{{ __('procflow::po.common.choose_material_placeholder') }}" wire:model.live="poForm.items.{{ $i }}.material_id" wire:change="onMaterialChanged({{ $i }}, $event.target.value)">
                                            @foreach($this->materials as $m)
                                                <flux:select.option value="{{ $m->id }}">{{ $m->sku }} - {{ $m->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.adhoc.placeholders.description_hint') }}"
                                            wire:model="poForm.items.{{ $i }}.description"
                                            class="min-w-64"
                                        />
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.common.note') }}"
                                            wire:model.live="poForm.items.{{ $i }}.note"
                                            class="min-w-56"
                                        />
                                    </td>
                                    <td class="py-2 px-3 w-28">
                                        <div class="w-28">
                                            <flux:input wire:model="poForm.items.{{ $i }}.unit_purchase" placeholder="{{ __('procflow::po.common.unit_example') }}"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model="poForm.items.{{ $i }}.qty_ordered"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="poForm.items.{{ $i }}.price_unit"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" step="0.0001" min="0" max="1" wire:model.live="poForm.items.{{ $i }}.tax_rate"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="poForm.items.{{ $i }}.desired_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="poForm.items.{{ $i }}.expected_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 align-top">
                                        @if ($this->activeGroups && $this->activeGroups->isNotEmpty())
                                            <div class="grid gap-2">
                                                @foreach ($this->activeGroups as $g)
                                                    <div class="flex items-center gap-2">
                                                        <flux:select wire:model.live="poForm.items.{{ $i }}.options.{{ $g->id }}" placeholder="{{ __('procflow::po.common.choose_placeholder') }}" label="{{ $g->name }}" class="min-w-56" >
                                                            <flux:select.option value="">—</flux:select.option>
                                                            @foreach (($this->activeOptionsByGroup[$g->id] ?? []) as $opt)
                                                                <flux:select.option value="{{ $opt['id'] }}">{{ $opt['name'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400 text-sm">{{ __('procflow::po.create.options.no_active_groups') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        <flux:button size="xs" variant="outline" wire:click="removePoItem({{ $i }})">{{ __('procflow::po.common.remove') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Supplier grouping preview --}}
            <div class="mt-4">
                <div class="text-sm text-neutral-600 mb-2">{{ __('procflow::po.create_preview.title') }}</div>
                @php $preview = $this->poSupplierPreview; @endphp
                @if (!empty($preview))
                    <div class="rounded border border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 text-amber-800 dark:text-amber-200">
                        <div class="font-medium mb-2">{{ __('procflow::po.create_preview.split_notice', ['count' => count($preview)]) }}</div>
                        <ul class="text-sm grid md:grid-cols-2 gap-2">
                            @foreach ($preview as $p)
                                <li class="flex items-center justify-between rounded border border-amber-200 dark:border-amber-800 bg-white dark:bg-neutral-900 px-3 py-2">
                                    <div>
                                        <div class="font-semibold">{{ $p['name'] }}</div>
                                        <div class="text-neutral-500">{{ __('procflow::po.create_preview.lines') }}: {{ $p['lines'] }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-neutral-500">{{ __('procflow::po.create_preview.subtotal_excl_tax') }}</div>
                                        <div class="font-semibold">{{ \Lastdino\ProcurementFlow\Support\Format::moneySubtotal($p['subtotal']) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="text-sm text-neutral-500">{{ __('procflow::po.create_preview.select_materials_hint') }}</div>
                @endif

                <div class="text-xs text-neutral-500 mt-2">{{ __('procflow::po.create_preview.no_adhoc_hint') }}</div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('create-po').close()">{{ __('procflow::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="savePoFromModal" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::po.buttons.create') }}</span>
                    <span wire:loading>{{ __('procflow::po.buttons.creating') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>


    {{-- Ad-hoc PO Modal (no material master) --}}
    <flux:modal wire:model.self="showAdhocPoModal" name="create-adhoc-po" class="w-full md:w-[64rem] max-w-full" :dismissible="false">
        <div class="w-full md:w-[64rem] max-w-full">
            <h3 class="text-lg font-semibold mb-3">{{ __('procflow::po.adhoc.title') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.adhoc.form.supplier') }}</label>
                    <select class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="adhocForm.supplier_id">
                        <option value="">{{ __('procflow::po.common.choose_placeholder') }}</option>
                        @foreach($this->suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                    @error('adhocForm.supplier_id') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.expected_date') }}</label>
                    <input type="date" class="w-full border rounded p-2 bg-white dark:bg-neutral-900" wire:model.live="adhocForm.expected_date">
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 mb-1">{{ __('procflow::po.common.requester') }}</label>
                    <input type="text" class="w-full border rounded p-2 bg-neutral-100 dark:bg-neutral-800" value="{{ auth()->user()->name ?? '—' }}" disabled>
                </div>
            </div>
            <flux:textarea
                label="{{ __('procflow::po.adhoc.delivery.label') }}"
                placeholder="{{ __('procflow::po.adhoc.delivery.placeholder') }}"
                wire:model.live="adhocForm.delivery_location"
            />

            <div class="space-y-3 mt-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-neutral-600">{{ __('procflow::po.adhoc.items.title') }}</div>
                    <flux:button size="sm" variant="outline" wire:click="addAdhocItem">{{ __('procflow::po.adhoc.items.add_row') }}</flux:button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-neutral-500">
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.description') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.manufacturer') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.note') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.unit') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.qty') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.unit_price') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.tax_rate') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.desired_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.expected_date') }}</th>
                                <th class="py-2 px-3">{{ __('procflow::po.adhoc.table.options') }}</th>
                                <th class="py-2 px-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adhocForm['items'] as $i => $row)
                                <tr class="border-t">
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.adhoc.placeholders.description_hint') }}"
                                            wire:model.live="adhocForm.items.{{ $i }}.description"
                                            class="min-w-64"
                                        />
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-40">
                                            <flux:input
                                                placeholder="{{ __('procflow::po.adhoc.placeholders.manufacturer_hint') }}"
                                                wire:model.live="adhocForm.items.{{ $i }}.manufacturer"
                                            />
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:textarea
                                            rows="2"
                                            placeholder="{{ __('procflow::po.common.note') }}"
                                            wire:model.live="adhocForm.items.{{ $i }}.note"
                                            class="min-w-56"
                                        />
                                    </td>
                                    <td class="py-2 px-3 w-28">
                                        <div class="w-28">
                                            <flux:input wire:model.live="adhocForm.items.{{ $i }}.unit_purchase" placeholder="{{ __('procflow::po.common.unit_example') }}"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="adhocForm.items.{{ $i }}.qty_ordered"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" wire:model.live="adhocForm.items.{{ $i }}.price_unit"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-28" type="number" step="0.0001" min="0" max="1" wire:model.live="adhocForm.items.{{ $i }}.tax_rate"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="adhocForm.items.{{ $i }}.desired_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="w-28">
                                            <flux:input class="w-40" type="date" wire:model.live="adhocForm.items.{{ $i }}.expected_date"/>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 align-top">
                                        @if ($this->activeGroups && $this->activeGroups->isNotEmpty())
                                            <div class="grid gap-2">
                                                @foreach ($this->activeGroups as $g)
                                                    <div class="flex items-center gap-2">
                                                        <flux:select wire:model.live="adhocForm.items.{{ $i }}.options.{{ $g->id }}" placeholder="{{ __('procflow::po.common.choose_placeholder') }}" label="{{ $g->name }}" class="min-w-56" >
                                                            <flux:select.option value="">—</flux:select.option>
                                                            @foreach (($this->activeOptionsByGroup[$g->id] ?? []) as $opt)
                                                                <flux:select.option value="{{ $opt['id'] }}">{{ $opt['name'] }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400 text-sm">{{ __('procflow::po.adhoc.options.no_active_groups') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        <flux:button size="xs" variant="ghost" wire:click="removeAdhocItem({{ $i }})">{{ __('procflow::po.common.remove') }}</flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <flux:button variant="outline" x-on:click="$flux.modal('create-adhoc-po').close()">{{ __('procflow::po.buttons.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveAdhocPoFromModal" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('procflow::po.buttons.save') }}</span>
                    <span wire:loading>{{ __('procflow::po.buttons.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>


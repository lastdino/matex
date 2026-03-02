<?php

declare(strict_types=1);

namespace Lastdino\Matex\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Lastdino\ApprovalFlow\Traits\HasApprovalFlow;
use Lastdino\Matex\Casts\PurchaseOrderStatusCast;
use Lastdino\Matex\Enums\PurchaseOrderStatus;
use Lastdino\Matex\Mail\PurchaseOrderIssuedMail;
use Lastdino\Matex\Services\PoNumberGenerator;
use Lastdino\Matex\Support\Tables;

class PurchaseOrder extends Model
{
    use HasApprovalFlow;

    protected $fillable = [
        'po_number', 'supplier_id', 'department_id', 'supplier_contact_id', 'status', 'issue_date', 'expected_date', 'subtotal', 'tax', 'total',
        'shipping_total', 'shipping_tax_total',
        'invoice_number', 'delivery_note_number', 'notes', 'created_by',
        // 発注ごとの納品先
        'delivery_location',
        // UI からの個別指定は廃止。サプライヤー設定に基づき自動送信する。
        'auto_send_to_supplier',
    ];

    public function getTable()
    {
        return Tables::name('purchase_orders');
    }

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'status' => PurchaseOrderStatusCast::class,
            'issue_date' => 'datetime',
            'expected_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'shipping_tax_total' => 'decimal:2',
            // 互換性のためにキャストは残すが、送信可否の判定には使用しない。
            'auto_send_to_supplier' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupplierContact::class, 'supplier_contact_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class);
    }

    /**
     * 承認完了時の処理：PO発行し、必要であればサプライヤーへメール送信
     */
    public function onApproved(): void
    {
        // 発行処理（Draftのみ）
        if ($this->status === PurchaseOrderStatus::Draft) {
            // 発番・Issued化
            $numbers = app(PoNumberGenerator::class);
            $this->po_number = $this->po_number ?: $numbers->generate(CarbonImmutable::now());
            $this->status = PurchaseOrderStatus::Issued;
            $this->issue_date = CarbonImmutable::now();
            // 送料は作成時にアイテムとして追加され、各合計に反映済みとする
            $this->shipping_total = 0;
            $this->shipping_tax_total = 0;
            $this->save();
        }

        // 自動送信可否はサプライヤーの設定で決定する
        /** @var Supplier|null $supplier */
        $supplier = $this->supplier;
        /** @var SupplierContact|null $contact */
        $contact = $this->contact;

        $shouldAutoSend = (bool) ($supplier?->getAttribute('auto_send_po') ?? false);
        if ($shouldAutoSend) {
            // 担当者が指定されていればそのメール、いなければサプライヤーのメール
            $to = $contact?->email ?: $supplier?->email;

            if (! empty($to)) {
                $mailable = new PurchaseOrderIssuedMail($this->fresh(['supplier', 'contact', 'items']));

                // CCは担当者のCCとサプライヤーのCCをマージ
                $ccs = [];
                $rawCcs = array_filter([$supplier?->email_cc, $contact?->email_cc]);

                if (! empty($rawCcs)) {
                    $combinedCc = implode(',', $rawCcs);
                    $ccs = array_values(array_filter(array_map(function ($v) {
                        return trim((string) $v);
                    }, explode(',', $combinedCc)), function ($v) {
                        return $v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL);
                    }));
                }

                $pending = Mail::to($to);
                if (! empty($ccs)) {
                    $pending = $pending->cc($ccs);
                }

                $pending->queue($mailable);
            }
        }
    }
}

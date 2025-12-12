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
        $po = $this->po->loadMissing(['supplier', 'items.material', 'requester']);
        $poNumber = $po->po_number ?: ('Draft-' . $po->getKey());

        // Choose From header in order of precedence (when enabled):
        // 1) Requester (PO creator) → if config 'use_requester' true and requester has email
        // 2) Package-level From config (address/name)
        // 3) Global mail.from (handled by Laravel when no explicit from set)
        /** @var array{address?:string|null,name?:string|null}|null $fromCfg */
        $fromCfg = (array) (config('procurement-flow.mail.from') ?? config('procurement_flow.mail.from') ?? []);
        $fromAddress = (string) ($fromCfg['address'] ?? '');
        $fromName = $fromCfg['name'] ?? null;
        $useRequester = (bool) ($fromCfg['use_requester'] ?? false);

        $requesterFromAddress = null;
        $requesterFromName = null;
        if ($useRequester) {
            $requester = $po->requester;
            $email = trim((string) ($requester?->email ?? ''));
            if ($email !== '') {
                $requesterFromAddress = $email;
                $requesterFromName = $requester?->name ?? null;
            }
        }

        $blade = Blade::render('procflow::pdf.purchase-order', compact('po'));

        $tmpPath = Chrome::pdfFromHtml($blade, [
            'printBackground' => true,
        ]);

        $mail = $this
            ->subject("【注文書】PO {$poNumber}")
            ->view('procflow::mail.purchase-orders.issued', [
                'po' => $po,
            ])
            ->attach($tmpPath, [
                'as'   => "PO-{$poNumber}.pdf",
                'mime' => 'application/pdf',
            ]);

        if ($requesterFromAddress) {
            $mail->from($requesterFromAddress, $requesterFromName);
        } elseif ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName);
        }

        return $mail;
    }
}

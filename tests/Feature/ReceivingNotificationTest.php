<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lastdino\Matex\Mail\ReceivingNotificationMail;
use Lastdino\Matex\Models\PurchaseOrder;
use Lastdino\Matex\Models\Receiving;
use Lastdino\Matex\Models\Supplier;
use Lastdino\Matex\Support\Settings;

uses(\Tests\TestCase::class, RefreshDatabase::class);

test('receiving notification is sent when enabled', function () {
    Mail::fake();

    Settings::saveNotification([
        'accounting_email' => 'accounting@example.com',
        'enable_receiving_notification' => true,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-001',
        'supplier_id' => $supplier->id,
    ]);

    $receiving = Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertSent(ReceivingNotificationMail::class, function ($mail) use ($receiving) {
        return $mail->hasTo('accounting@example.com') &&
               $mail->receiving->id === $receiving->id;
    });
});

test('receiving notification is not sent when disabled', function () {
    Mail::fake();

    Settings::saveNotification([
        'accounting_email' => 'accounting@example.com',
        'enable_receiving_notification' => false,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-002',
        'supplier_id' => $supplier->id,
    ]);

    Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertNotSent(ReceivingNotificationMail::class);
});

test('receiving notification is not sent when email is empty', function () {
    Mail::fake();

    Settings::saveNotification([
        'accounting_email' => '',
        'enable_receiving_notification' => true,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-003',
        'supplier_id' => $supplier->id,
    ]);

    Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertNotSent(ReceivingNotificationMail::class);
});

test('receiving notification is sent with custom name when enabled', function () {
    Mail::fake();

    Settings::saveNotification([
        'accounting_email' => 'accounting@example.com',
        'accounting_name' => 'カスタム経理部御中',
        'enable_receiving_notification' => true,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-004',
        'supplier_id' => $supplier->id,
    ]);

    $receiving = Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertSent(ReceivingNotificationMail::class, function ($mail) {
        $mail->build(); // Load relations and settings inside mail
        $html = view($mail->view, array_merge($mail->viewData, ['receiving' => $mail->receiving]))->render();

        return str_contains($html, 'カスタム経理部御中');
    });
});

test('receiving notification is sent to requester when enabled', function () {
    Mail::fake();

    $user = \App\Models\User::factory()->create(['email' => 'requester@example.com']);

    Settings::saveNotification([
        'enable_requester_receiving_notification' => true,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-REQ-001',
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
    ]);

    $receiving = Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertSent(ReceivingNotificationMail::class, function ($mail) use ($receiving) {
        return $mail->hasTo('requester@example.com') &&
               $mail->receiving->id === $receiving->id;
    });
});

test('receiving notification is not sent to requester when disabled', function () {
    Mail::fake();

    $user = \App\Models\User::factory()->create(['email' => 'requester@example.com']);

    Settings::saveNotification([
        'enable_requester_receiving_notification' => false,
    ]);

    $supplier = Supplier::query()->create(['name' => 'Test Supplier']);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-REQ-002',
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
    ]);

    Receiving::query()->create([
        'purchase_order_id' => $po->id,
        'received_at' => now(),
    ]);

    Mail::assertNotSent(ReceivingNotificationMail::class);
});

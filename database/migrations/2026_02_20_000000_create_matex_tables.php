<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lastdino\Matex\Support\Tables;

return new class extends Migration
{
    public function up(): void
    {
        // material_categories
        Schema::create(Tables::name('material_categories'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // suppliers
        Schema::create(Tables::name('suppliers'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('email_cc')->nullable();
            $table->boolean('auto_send_po')->default(false);
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // storage_locations
        Schema::create(Tables::name('storage_locations'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('fire_service_law_category')->nullable();
            $table->decimal('max_specified_quantity_ratio', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // materials
        Schema::create(Tables::name('materials'), function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('tax_code', 32)->nullable();
            $table->string('unit_stock', 32);
            $table->decimal('min_stock', 18, 6)->nullable();
            $table->decimal('max_stock', 18, 6)->nullable();
            $table->foreignId('category_id')->nullable()->constrained(Tables::name('material_categories'));
            $table->decimal('current_stock', 18, 6)->nullable();
            // additional attributes
            $table->string('manufacturer_name')->nullable();
            $table->string('applicable_regulation')->nullable();
            $table->string('ghs_mark')->nullable();
            $table->string('protective_equipment')->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('moq', 24, 6)->nullable();
            $table->decimal('pack_size', 24, 6)->nullable();
            $table->boolean('separate_shipping')->default(false);
            $table->decimal('shipping_fee_per_order', 10, 2)->nullable();
            $table->string('unit_purchase_default', 32)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained(Tables::name('suppliers'));
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_to_monox')->default(false);
            $table->string('monox_item_id')->nullable();
            $table->text('default_purchase_note')->nullable();
            $table->boolean('is_chemical')->default(false);
            $table->string('cas_no')->nullable();
            $table->string('physical_state')->nullable();
            $table->text('ghs_hazard_details')->nullable();
            $table->decimal('specified_quantity', 18, 6)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('disposal_method')->nullable();
            $table->timestamps();
        });

        // unit_conversions
        Schema::create(Tables::name('unit_conversions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained(Tables::name('materials'));
            $table->string('from_unit', 32)->nullable();
            $table->string('to_unit', 32)->nullable();
            $table->decimal('factor', 18, 6);
            $table->timestamps();
            $table->unique(['material_id', 'from_unit', 'to_unit'], 'pf_unit_conv_unique');
        });

        // material_risk_assessments
        Schema::create(Tables::name('material_risk_assessments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained(Tables::name('materials'))->cascadeOnDelete();
            $table->date('assessment_date');
            $table->string('risk_level')->nullable();
            $table->text('assessment_results')->nullable();
            $table->text('countermeasures')->nullable();
            $table->date('next_assessment_date')->nullable();
            $table->string('assessor_name')->nullable();
            $table->timestamps();
        });

        // material_inspections
        Schema::create(Tables::name('material_inspections'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained(Tables::name('materials'))->cascadeOnDelete();
            $table->date('inspection_date');
            $table->string('inspector_name')->nullable();
            $table->boolean('container_status')->default(true);
            $table->boolean('label_status')->default(true);
            $table->text('details')->nullable();
            $table->timestamps();
        });

        // purchase_orders
        Schema::create(Tables::name('purchase_orders'), function (Blueprint $table): void {
            $table->id();
            $table->string('po_number')->unique()->nullable();
            $table->foreignId('supplier_id')->constrained(Tables::name('suppliers'));
            $table->string('status', 32)->default('draft');
            $table->timestamp('issue_date')->nullable();
            $table->timestamp('expected_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('shipping_total', 18, 2)->default(0);
            $table->decimal('shipping_tax_total', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->text('delivery_location')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('delivery_note_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('auto_send_to_supplier')->default(false);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        // option groups and options (used by PO item attributes)
        Schema::create(Tables::name('option_groups'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create(Tables::name('options'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained(Tables::name('option_groups'));
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        // purchase_order_items
        Schema::create(Tables::name('purchase_order_items'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained(Tables::name('purchase_orders'));
            $table->foreignId('material_id')->nullable()->constrained(Tables::name('materials'));
            $table->uuid('scan_token')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->unsignedBigInteger('shipping_for_item_id')->nullable();
            $table->string('unit_purchase', 32);
            $table->decimal('qty_ordered', 18, 6);
            $table->decimal('qty_canceled', 18, 6)->default(0);
            $table->decimal('price_unit', 18, 6);
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->date('desired_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->foreign('shipping_for_item_id', 'pf_poi_ship_for_fk')
                ->references('id')
                ->on(Tables::name('purchase_order_items'))
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // receivings
        Schema::create(Tables::name('receivings'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained(Tables::name('purchase_orders'));
            $table->timestamp('received_at');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        // receiving_items
        Schema::create(Tables::name('receiving_items'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receiving_id')->constrained(Tables::name('receivings'));
            $table->foreignId('purchase_order_item_id')->constrained(Tables::name('purchase_order_items'));
            $table->foreignId('material_id')->nullable()->constrained(Tables::name('materials'));
            $table->string('unit_purchase', 32);
            $table->decimal('qty_received', 18, 6);
            $table->decimal('qty_base', 18, 6);
            $table->timestamps();
            $table->index(['receiving_id']);
        });

        // material lots
        Schema::create(Tables::name('material_lots'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained(Tables::name('materials'))->cascadeOnDelete();
            $table->string('lot_no');
            $table->foreignId('storage_location_id')->nullable()->constrained(Tables::name('storage_locations'))->nullOnDelete();
            $table->decimal('qty_on_hand', 24, 6)->default(0);
            $table->string('unit', 32)->nullable();
            $table->dateTime('received_at')->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('barcode')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained(Tables::name('suppliers'))->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained(Tables::name('purchase_orders'))->nullOnDelete();
            $table->timestamps();
            $table->unique(['material_id', 'lot_no', 'storage_location_id'], Tables::name('material_lots').'_mat_lot_loc_unique');
        });

        // stock movements
        Schema::create(Tables::name('stock_movements'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained(Tables::name('materials'))->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained(Tables::name('material_lots'))->nullOnDelete();
            $table->string('type', 32); // in|out|adjust|transfer_in|transfer_out|return|scrap
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('qty_base', 24, 6);
            $table->string('unit', 32)->nullable();
            $table->dateTime('occurred_at');
            $table->string('reason')->nullable();
            $table->boolean('is_external_sync')->default(false);
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->timestamps();
            $table->index(['material_id', 'occurred_at']);
            $table->index(['causer_type', 'causer_id']);
        });

        // app_settings
        Schema::create(Tables::name('app_settings'), function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // PO item option values (pivot-like)
        Schema::create(Tables::name('po_item_option_values'), function (Blueprint $table): void {
            $table->id();

            // Use explicit, short foreign key names to satisfy MariaDB's 64-char identifier limit
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('option_id');

            $table->timestamps();

            // indexes & uniques
            $table->unique(['purchase_order_item_id', 'group_id'], Tables::name('piv_item_group_unique'));
            $table->index(['option_id'], Tables::name('piv_option_idx'));

            // foreign keys with short names
            $table->foreign('purchase_order_item_id', 'pf_piv_po_item_fk')
                ->references('id')
                ->on(Tables::name('purchase_order_items'))
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('group_id', 'pf_piv_group_fk')
                ->references('id')
                ->on(Tables::name('option_groups'))
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('option_id', 'pf_piv_option_fk')
                ->references('id')
                ->on(Tables::name('options'))
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // Ordering tokens for QR/links
        Schema::create(Tables::name('ordering_tokens'), function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->unsignedBigInteger('material_id');
            $table->string('unit_purchase')->nullable();
            $table->decimal('default_qty', 24, 6)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('material_id')
                ->references('id')
                ->on(Tables::name('materials'))
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('ordering_tokens'));
        Schema::dropIfExists(Tables::name('po_item_option_values'));
        Schema::dropIfExists(Tables::name('app_settings'));
        Schema::dropIfExists(Tables::name('stock_movements'));
        Schema::dropIfExists(Tables::name('material_lots'));
        Schema::dropIfExists(Tables::name('receiving_items'));
        Schema::dropIfExists(Tables::name('receivings'));
        Schema::dropIfExists(Tables::name('purchase_order_items'));
        Schema::dropIfExists(Tables::name('options'));
        Schema::dropIfExists(Tables::name('option_groups'));
        Schema::dropIfExists(Tables::name('purchase_orders'));
        Schema::dropIfExists(Tables::name('material_inspections'));
        Schema::dropIfExists(Tables::name('material_risk_assessments'));
        Schema::dropIfExists(Tables::name('unit_conversions'));
        Schema::dropIfExists(Tables::name('materials'));
        Schema::dropIfExists(Tables::name('storage_locations'));
        Schema::dropIfExists(Tables::name('suppliers'));
        Schema::dropIfExists(Tables::name('material_categories'));
    }
};

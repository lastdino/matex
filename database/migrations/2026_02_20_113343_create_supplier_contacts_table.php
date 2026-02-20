<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lastdino\Matex\Support\Tables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Tables::name('supplier_contacts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained(Tables::name('suppliers'))->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('email_cc')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add contact column to materials
        Schema::table(Tables::name('materials'), function (Blueprint $table) {
            $table->foreignId('preferred_supplier_contact_id')->nullable()->constrained(Tables::name('supplier_contacts'))->nullOnDelete();
        });

        // Add contact column to purchase_orders
        Schema::table(Tables::name('purchase_orders'), function (Blueprint $table) {
            $table->foreignId('supplier_contact_id')->nullable()->constrained(Tables::name('supplier_contacts'))->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('purchase_orders'), function (Blueprint $table) {
            $table->dropForeign(['supplier_contact_id']);
            $table->dropColumn('supplier_contact_id');
        });

        Schema::table(Tables::name('materials'), function (Blueprint $table) {
            $table->dropForeign(['preferred_supplier_contact_id']);
            $table->dropColumn('preferred_supplier_contact_id');
        });

        Schema::dropIfExists(Tables::name('supplier_contacts'));
    }
};

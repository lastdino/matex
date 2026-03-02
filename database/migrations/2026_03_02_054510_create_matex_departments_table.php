<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Lastdino\Matex\Support\Tables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(Tables::name('departments'), function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('部門コード');
            $table->string('name')->comment('部門名');
            $table->string('monox_api_key')->nullable()->comment('monox連携用APIキー');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ordering_tokens に department_id を追加 (オプション項目から独立させるため)
        Schema::table(Tables::name('ordering_tokens'), function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('material_id')->constrained(Tables::name('departments'))->nullOnDelete();
        });

        // stock_movements に department_id を追加 (どの部門の実績か保持するため)
        Schema::table(Tables::name('stock_movements'), function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('material_id')->constrained(Tables::name('departments'))->nullOnDelete();
        });

        // purchase_orders に department_id を追加
        Schema::table(Tables::name('purchase_orders'), function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('supplier_id')->constrained(Tables::name('departments'))->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('purchase_orders'), function (Blueprint $table) {
            $table->dropForeign([ 'department_id' ]);
            $table->dropColumn('department_id');
        });

        Schema::table(Tables::name('stock_movements'), function (Blueprint $table) {
            $table->dropForeign([ 'department_id' ]);
            $table->dropColumn('department_id');
        });

        Schema::table(Tables::name('ordering_tokens'), function (Blueprint $table) {
            $table->dropForeign([ 'department_id' ]);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists(Tables::name('departments'));
    }
};

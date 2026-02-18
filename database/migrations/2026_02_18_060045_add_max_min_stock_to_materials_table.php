<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lastdino\ProcurementFlow\Support\Tables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(Tables::name('materials'), function (Blueprint $table) {
            $table->decimal('min_stock', 18, 6)->nullable()->after('safety_stock')->comment('Minimum storage quantity');
            $table->decimal('max_stock', 18, 6)->nullable()->after('min_stock')->comment('Maximum storage quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('materials'), function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};

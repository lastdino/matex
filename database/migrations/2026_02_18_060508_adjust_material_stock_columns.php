<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'procurement_flow_materials';
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('min_stock');
            $table->renameColumn('safety_stock', 'min_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'procurement_flow_materials';
        Schema::table($tableName, function (Blueprint $table) {
            $table->renameColumn('min_stock', 'safety_stock');
            $table->decimal('min_stock', 18, 6)->nullable()->after('safety_stock');
        });
    }
};

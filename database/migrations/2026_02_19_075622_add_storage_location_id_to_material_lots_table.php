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
        Schema::table(Tables::name('material_lots'), function (Blueprint $table) {
            $table->foreignId('storage_location_id')->nullable()->after('lot_no')->constrained(Tables::name('storage_locations'))->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('material_lots'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });
    }
};

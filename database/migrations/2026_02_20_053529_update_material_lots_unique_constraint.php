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
            // Drop old unique constraint
            $table->dropUnique([
                'material_id',
                'lot_no',
            ]);

            // Add new unique constraint including storage_location_id
            $table->unique([
                'material_id',
                'lot_no',
                'storage_location_id',
            ], Tables::name('material_lots').'_mat_lot_loc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('material_lots'), function (Blueprint $table) {
            $table->dropUnique(Tables::name('material_lots').'_mat_lot_loc_unique');
            $table->unique(['material_id', 'lot_no']);
        });
    }
};

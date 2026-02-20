<?php

declare(strict_types=1);

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
        Schema::table(Tables::name('suppliers'), function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table(Tables::name('material_categories'), function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Tables::name('suppliers'), function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });

        Schema::table(Tables::name('material_categories'), function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
        });
    }
};

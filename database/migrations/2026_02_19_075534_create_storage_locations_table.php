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
        Schema::create(Tables::name('storage_locations'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('fire_service_law_category')->nullable(); // 消防法上の種別 (e.g., 屋内貯蔵所, 一般取扱所)
            $table->decimal('max_specified_quantity_ratio', 10, 2)->nullable(); // 指定数量の最大倍率 (e.g., 1.0, 0.9)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Tables::name('storage_locations'));
    }
};

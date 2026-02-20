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
        Schema::table(Tables::name('purchase_order_items'), function (Blueprint $table): void {
            $table->string('manufacturer')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table(Tables::name('purchase_order_items'), function (Blueprint $table): void {
            $table->dropColumn('manufacturer');
        });
    }
};

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
        Schema::table(Tables::name('purchase_order_items'), function (Blueprint $table) {
            $table->decimal('qty_canceled', 18, 6)->default(0)->after('qty_ordered');
            $table->timestamp('canceled_at')->nullable()->after('expected_date');
            $table->text('canceled_reason')->nullable()->after('canceled_at');
        });
    }

    public function down(): void
    {
        Schema::table(Tables::name('purchase_order_items'), function (Blueprint $table) {
            $table->dropColumn(['qty_canceled', 'canceled_at', 'canceled_reason']);
        });
    }
};

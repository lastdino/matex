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
        // SQLite supports foreign key changes by recreating the table, but Laravel's Schema::table might handle it if correctly configured.
        // If the configuration changes the table name, the foreign key must be updated.

        try {
            Schema::table(Tables::name('purchase_orders'), function (Blueprint $table) {
                // Drop and recreate foreign key constraint to point to the current departments table
                $table->dropForeign(['department_id']);
                $table->foreign('department_id')
                    ->references('id')
                    ->on(Tables::name('departments'))
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Log or handle the exception if foreign key doesn't exist
            \Illuminate\Support\Facades\Log::warning('Could not fix foreign key on purchase_orders: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to revert this perfectly without knowing the previous config.
    }
};

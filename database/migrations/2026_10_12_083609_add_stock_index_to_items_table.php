<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add composite index untuk optimasi query low stock items di dashboard.
     * Query: whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0)
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Composite index untuk query low stock
            // Format: items_stock_min_stock_index
            $table->index(['stock', 'min_stock'], 'items_stock_min_stock_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_stock_min_stock_index');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan index pada kolom yang sering digunakan untuk query
     * untuk meningkatkan performa sistem secara signifikan.
     */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // Index untuk filtering berdasarkan tanggal pengambilan (digunakan di reports)
            $table->index('taken_at', 'idx_withdrawals_taken_at');
            
            // Index untuk search berdasarkan nama pengambil
            $table->index('taken_by', 'idx_withdrawals_taken_by');
        });

        Schema::table('items', function (Blueprint $table) {
            // Index untuk search berdasarkan nama barang
            $table->index('name', 'idx_items_name');
            
            // Composite index untuk optimasi search simultan item_code dan name
            $table->index(['item_code', 'name'], 'idx_items_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex('idx_withdrawals_taken_at');
            $table->dropIndex('idx_withdrawals_taken_by');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_name');
            $table->dropIndex('idx_items_search');
        });
    }
};

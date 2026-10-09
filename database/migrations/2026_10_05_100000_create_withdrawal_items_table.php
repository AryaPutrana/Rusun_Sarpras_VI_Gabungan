<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel detail pengambilan: satu withdrawal (transaksi) bisa berisi banyak barang.
     */
    public function up(): void
    {
        Schema::create('withdrawal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            // Satu barang hanya boleh muncul satu kali per transaksi.
            // Pengaman lapis kedua selain validasi "distinct" di controller.
            $table->unique(['withdrawal_id', 'item_id'], 'withdrawal_items_withdrawal_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel audit untuk setiap perubahan stok manual dari form edit barang.
     * Withdrawals sudah punya jejak (taken_by + description), tapi stock
     * adjustment dari form barang tidak punya jejak sama sekali sebelum ini.
     */
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('type', ['add', 'subtract']);
            $table->unsignedInteger('quantity');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};

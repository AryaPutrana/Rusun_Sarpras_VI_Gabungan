<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pindahkan data withdrawal lama (1 baris = 1 barang) ke struktur baru:
     * header (withdrawals) + detail (withdrawal_items).
     */
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
        });

        // Kolom lama masih ada pada tahap ini, jadi datanya bisa disalin apa adanya.
        $legacy = DB::table('withdrawals')
            ->select('id', 'item_id', 'quantity', 'unit_price', 'subtotal')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($legacy as $row) {
            $rows[] = [
                'withdrawal_id' => $row->id,
                'item_id' => $row->item_id,
                'quantity' => $row->quantity,
                'unit_price' => $row->unit_price,
                'subtotal' => $row->subtotal,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= 500) {
                DB::table('withdrawal_items')->insert($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            DB::table('withdrawal_items')->insert($rows);
        }

        // Total diambil langsung dari kolom lama supaya nilai historis tidak berubah.
        DB::table('withdrawals')->update([
            'total_quantity' => DB::raw('quantity'),
            'total_value' => DB::raw('subtotal'),
        ]);
    }

    public function down(): void
    {
        // Data tidak bisa dipisah kembali dengan aman: satu baris lama bisa jadi
        // banyak baris detail setelah dipakai aplikasi. Karena itu down() hanya
        // mengembalikan skema. Riwayat withdrawal tetap tersimpan utuh.
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['total_quantity', 'total_value']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buang kolom lama dari tabel withdrawals.
     *
     * Data sudah disalin ke withdrawal_items oleh migrasi sebelumnya, jadi kolom
     * item_id / quantity / unit_price / subtotal hanya sisa struktur "1 barang
     * per transaksi". ItemController::destroy() kini mengecek lewat tabel detail.
     *
     * SQLite tidak bisa melepas foreign key maupun drop kolom yang dipakai FK,
     * jadi tabel dibuat ulang. MySQL cukup drop kolom biasa.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildForSqlite();

            return;
        }

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign('withdrawals_item_id_foreign');
            $table->dropForeign('withdrawals_rusun_id_foreign');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['item_id', 'quantity', 'unit_price', 'subtotal']);
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreign('rusun_id')->references('id')->on('rusun')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildBackForSqlite();

            return;
        }

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
        });

        $this->restoreLegacyValues();
    }

    /**
     * Kembalikan kolom lama dari baris detail pertama tiap transaksi.
     * Transaksi yang punya banyak detail hanya bisa mewakili satu barang,
     * jadi nilai yang dipulihkan adalah detail pertama (identik dengan
     * kondisi sebelum migrasi).
     */
    private function restoreLegacyValues(): void
    {
        DB::table('withdrawals')->orderBy('id')->chunk(200, function ($withdrawals) {
            foreach ($withdrawals as $withdrawal) {
                $first = DB::table('withdrawal_items')
                    ->where('withdrawal_id', $withdrawal->id)
                    ->orderBy('id')
                    ->first();

                DB::table('withdrawals')
                    ->where('id', $withdrawal->id)
                    ->update([
                        'item_id' => $first->item_id ?? null,
                        'quantity' => $first->quantity ?? 0,
                        'unit_price' => $first->unit_price ?? 0,
                        'subtotal' => $first->subtotal ?? 0,
                    ]);
            }
        });
    }

    /**
     * SQLite: buat ulang tabel withdrawals tanpa kolom legacy.
     * foreign_keys dimatikan sementara agar tabel bisa dilepas & diganti nama.
     */
    private function rebuildForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::dropIfExists('withdrawals_new');

        Schema::create('withdrawals_new', function (Blueprint $table) {
            $table->id();
            $table->string('taken_by', 255);
            $table->foreignId('rusun_id')->constrained('rusun')->restrictOnDelete();
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->dateTime('taken_at');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $rows = DB::table('withdrawals')
            ->select('id', 'taken_by', 'rusun_id', 'total_quantity', 'total_value', 'taken_at', 'description', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table('withdrawals_new')->insert((array) $row);
        }

        // Dibaca dulu ke memori, lalu tabel lama dilepas. Nama index SQLite bersifat
        // global, jadi tabel detail harus dibuat setelah nama aslinya bebas.
        $items = DB::table('withdrawal_items')
            ->select('withdrawal_id', 'item_id', 'quantity', 'unit_price', 'subtotal', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();

        Schema::dropIfExists('withdrawal_items');
        Schema::dropIfExists('withdrawals');

        Schema::rename('withdrawals_new', 'withdrawals');

        Schema::create('withdrawal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->unique(['withdrawal_id', 'item_id'], 'withdrawal_items_withdrawal_item_unique');
        });

        foreach (array_chunk($items, 500) as $chunk) {
            if (! empty($chunk)) {
                DB::table('withdrawal_items')->insert($chunk);
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * SQLite: kembalikan kolom legacy. Rollback parsial: transaksi yang sudah
     * punya banyak detail hanya dipulangkan ke detail pertamanya.
     */
    private function rebuildBackForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::dropIfExists('withdrawals_new');

        Schema::create('withdrawals_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained('items')->cascadeOnDelete();
            $table->string('taken_by', 255);
            $table->foreignId('rusun_id')->constrained('rusun')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->dateTime('taken_at');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $rows = DB::table('withdrawals')
            ->select('id', 'taken_by', 'rusun_id', 'total_quantity', 'total_value', 'taken_at', 'description', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $first = DB::table('withdrawal_items')
                ->where('withdrawal_id', $row->id)
                ->orderBy('id')
                ->first();

            DB::table('withdrawals_new')->insert([
                'id' => $row->id,
                'item_id' => $first->item_id ?? null,
                'taken_by' => $row->taken_by,
                'rusun_id' => $row->rusun_id,
                'quantity' => $first->quantity ?? 0,
                'unit_price' => $first->unit_price ?? 0,
                'subtotal' => $first->subtotal ?? 0,
                'total_quantity' => $row->total_quantity,
                'total_value' => $row->total_value,
                'taken_at' => $row->taken_at,
                'description' => $row->description,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::dropIfExists('withdrawal_items');
        Schema::dropIfExists('withdrawals');

        Schema::rename('withdrawals_new', 'withdrawals');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};

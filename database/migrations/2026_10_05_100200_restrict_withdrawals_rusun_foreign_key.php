<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cegah riwayat pengambilan terhapus diam-diam.
     *
     * Sebelumnya rusun_id memakai cascade: menghapus satu rusun ikut menghapus
     * semua withdrawal tanpa jejak. ItemController::destroy() sudah menolak
     * menghapus barang yang punya riwayat, jadi aturan ini disamakan di database.
     *
     * SQLite tidak bisa melepas foreign key tanpa membuat ulang tabel, jadi
     * migrasi ini dilewati di SQLite (dipakai oleh test). Aturan hapus tetap
     * ditegakkan di aplikasi pada kedua driver.
     */
    public function up(): void
    {
        $this->swapRusunForeignKey('restrict');
    }

    public function down(): void
    {
        $this->swapRusunForeignKey('cascade');
    }

    private function swapRusunForeignKey(string $onDelete): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign('withdrawals_rusun_id_foreign');
        });

        Schema::table('withdrawals', function (Blueprint $table) use ($onDelete) {
            $table->foreign('rusun_id')
                ->references('id')
                ->on('rusun')
                ->onDelete($onDelete);
        });
    }
};

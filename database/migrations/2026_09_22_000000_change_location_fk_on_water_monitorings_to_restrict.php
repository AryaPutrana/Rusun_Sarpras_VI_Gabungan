<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ganti foreign key location_id pada water_monitorings dari
     * cascadeOnDelete menjadi restrictOnDelete. Lokasi yang masih memiliki
     * data pemeriksaan tidak dapat dihapus pada level database
     * (lapis kedua setelah guard di controller).
     *
     * Index kolom location_id tetap tersedia melalui unique index
     * (location_id, tanggal, sesi) sehingga foreign key baru tidak
     * membutuhkan index tambahan.
     */
    public function up(): void
    {
        Schema::table('water_monitorings', function (Blueprint $table) {
            $table->dropForeign(['location_id']);

            $table->foreign('location_id')
                ->references('id')
                ->on('monitoring_locations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_monitorings', function (Blueprint $table) {
            $table->dropForeign(['location_id']);

            $table->foreign('location_id')
                ->constrained('monitoring_locations')
                ->cascadeOnDelete();
        });
    }
};
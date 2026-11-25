<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            Schema::table('finance_reports', function (Blueprint $table) {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('finance_locations')
                    ->nullOnDelete();
            });

            $now = now();

            // Cek apakah lokasi 'Umum' sudah ada sebelum insert
            $existing = DB::table('finance_locations')
                ->where('nama_lokasi', 'Umum')
                ->first();

            if ($existing) {
                $umumId = $existing->id;
            } else {
                $umumId = DB::table('finance_locations')->insertGetId([
                    'nama_lokasi' => 'Umum',
                    'keterangan' => 'Lokasi default untuk data sebelum pembaruan.',
                    'status' => 'aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('finance_reports')
                ->whereNull('location_id')
                ->update(['location_id' => $umumId]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};

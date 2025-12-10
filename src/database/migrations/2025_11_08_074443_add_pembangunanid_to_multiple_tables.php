<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Daftar tabel yang mau ditambah kolom pembangunanId
    private array $tables = [
        'usulan_fisik_bsl',
        'psu_usulan_fisik_perumahans',
        'psu_usulan_fisik_tpus',
        'psu_usulan_fisik_pjls',
        'psu_serah_terimas',
        'rutilahus',
        'permukimans',
        'usulan_sapds_individuals',
        'usulan_sapds_fasilitas_umums',
        'sapd_lahan_masyarakats',
        'perencanaans',
        // tambahkan tabel lain jika perlu
    ];

    public function up(): void
    {
        foreach ($this->tables as $tName) {
            // Lewati jika tabel belum ada atau kolom sudah ada
            if (!Schema::hasTable($tName) || Schema::hasColumn($tName, 'pembangunanId')) {
                continue;
            }

            Schema::table($tName, function (Blueprint $table) use ($tName) {
                // Kalau tabel punya 'pesan_verifikasi', taruh setelah itu
                if (Schema::hasColumn($tName, 'pesan_verifikasi')) {
                    $table->char('pembangunanId', 36)->nullable()->after('pesan_verifikasi');
                } else {
                    // Fallback: taruh di akhir tabel (hindari error "Unknown column ...")
                    $table->char('pembangunanId', 36)->nullable();
                }

                // Optional index biar query cepat
                $table->index('pembangunanId', "{$tName}_pembangunanId_idx");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tName) {
            if (!Schema::hasTable($tName) || !Schema::hasColumn($tName, 'pembangunanId')) {
                continue;
            }

            Schema::table($tName, function (Blueprint $table) use ($tName) {
                // Hapus index kalau ada, lalu hapus kolom
                try { $table->dropIndex("{$tName}_pembangunanId_idx"); } catch (\Throwable $e) {}
                $table->dropColumn('pembangunanId');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permukimans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('sumber_usulan');
            $table->string('jenis_usulan');
            $table->string('nama_pengusul');
            $table->string('no_kontak_pengusul')->nullable();
            $table->string('email')->nullable();

            $table->string('instansi')->nullable();
            $table->string('alamat_dusun_instansi')->nullable();
            $table->string('alamat_rt_instansi')->nullable();
            $table->string('alamat_rw_instansi')->nullable();

            $table->date('tanggal_usulan')->nullable();

            $table->string('nama_pic')->nullable();
            $table->string('no_kontak_pic')->nullable();

            $table->string('status_tanah')->nullable();

            // FILE ARRAYS (JSON)
            $table->json('foto_sertifikat_status_tanah')->nullable();

            $table->string('panjang_usulan')->nullable();

            $table->string('alamat_dusun_usulan')->nullable();
            $table->string('alamat_rt_usulan')->nullable();
            $table->string('alamat_rw_usulan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();

            $table->string('titik_lokasi')->nullable();

            // FILE ARRAYS (JSON)
            $table->json('foto_sta0')->nullable();
            $table->json('foto_sta100')->nullable();
            $table->json('surat_pemohonan')->nullable();
            $table->json('proposal_usulan')->nullable();

            // Verifikasi
            $table->unsignedTinyInteger('status_verifikasi')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable(); // NEW

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permukimans');
    }
};

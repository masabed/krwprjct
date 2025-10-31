<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psu_usulan_fisik_tpu', function (Blueprint $table) {
            // PK UUID sebagai string
            $table->uuid('uuid')->primary();

            // Keterangan permohonan
            $table->string('tanggalPermohonan', 30);
            $table->string('nomorSuratPermohonan', 255);

            // Sumber usulan & Data pemohon
            $table->string('sumberUsulan', 100);
            $table->string('namaAspirator', 150);
            $table->string('noKontakAspirator', 50);
            $table->string('namaPIC', 150);
            $table->string('noKontakPIC', 50);

            // Rincian usulan
            $table->string('jenisUsulan', 100);
            $table->text('uraianMasalah')->nullable();

            // Dimensi/eksisting
            $table->string('luasTPUEksisting', 100)->nullable();

            // Lokasi usulan
            $table->string('alamatUsulan', 500);
            $table->string('rtUsulan', 10)->nullable();
            $table->string('rwUsulan', 10)->nullable();
            $table->string('kecamatanUsulan', 150);
            $table->string('kelurahanUsulan', 150);
            $table->string('titikLokasiUsulan', 255)->nullable();
            $table->string('jenisLokasi', 100)->nullable();

            // Keterangan lokasi (relasi opsional ke perumahan)
            $table->uuid('perumahanId')->nullable()->index();
            $table->string('statusTanah', 150)->nullable();

            // Dokumen pendukung (JSON array of UUID)
            $table->json('suratPermohonanUsulanFisik')->nullable();
            $table->json('proposalUsulanFisik')->nullable();
            $table->json('sertifikatStatusTanah')->nullable(); // boleh null
            $table->json('dokumentasiEksisting')->nullable();

            // Status + catatan (optional, kalau mau dipakai)
            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable();

            // Ownership (opsional)
            $table->uuid('user_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psu_usulan_fisik_tpu');
    }
};

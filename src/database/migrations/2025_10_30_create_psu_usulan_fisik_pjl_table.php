<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psu_usulan_fisik_pjl', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // PK UUID
            $table->uuid('uuid')->primary();

            // Keterangan permohonan
            $table->string('tanggalPermohonan', 25);
            $table->string('nomorSuratPermohonan', 150);

            // Sumber usulan & Data pemohon
            $table->string('sumberUsulan', 150);
            $table->string('namaAspirator', 150);
            $table->string('noKontakAspirator', 50);
            $table->string('namaPIC', 150);
            $table->string('noKontakPIC', 50);

            // Rincian usulan
            $table->string('jenisUsulan', 150);
            $table->text('uraianMasalah');

            // Dimensi usulan / Eksisting
            $table->string('panjangJalanEksisting', 100)->nullable();
            $table->string('jumlahTitikPJLEksisting', 100)->nullable();

            // Lokasi usulan
            $table->string('alamatUsulan', 255);
            $table->string('rtUsulan', 10)->nullable();
            $table->string('rwUsulan', 10)->nullable();
            $table->string('rayonUsulan', 100)->nullable();
            $table->string('kecamatanUsulan', 150);
            $table->string('kelurahanUsulan', 150);
            $table->string('titikLokasiUsulan', 255)->nullable();
            $table->string('jenisLokasi', 100)->nullable();

            // Keterangan lokasi BSL
            $table->uuid('perumahanId')->nullable();
            $table->string('statusJalan', 150)->nullable();

            // Dokumen pendukung (JSON arrays of UUID)
            $table->json('suratPermohonanUsulanFisik')->nullable();
            $table->json('proposalUsulanFisik')->nullable();
            $table->json('dokumentasiEksisting')->nullable();

            // Meta
            $table->uuid('user_id'); // pemilik data
            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psu_usulan_fisik_pjl');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpu_serah_terimas', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relasi & identitas
            $table->uuid('perumahanId')->index();   // UUID Perumahan yang dipilih
            $table->string('user_id')->nullable();  // user pembuat

            // Data pemohon
            $table->string('tipePengaju', 100)->nullable();
            $table->string('namaPemohon', 255)->nullable();
            $table->string('nikPemohon', 100)->nullable();

            // Data developer/pengaju
            $table->string('jenisDeveloper', 100)->nullable();
            $table->string('namaDeveloper', 255)->nullable();
            $table->string('alamatDeveloper', 500)->nullable();
            $table->string('rtDeveloper', 10)->nullable();
            $table->string('rwDeveloper', 10)->nullable();

            // Kontak
            $table->string('noKontak', 100)->nullable();
            $table->string('email', 255)->nullable();

            // Administratif
            $table->date('tanggalPengusulan')->nullable();
            $table->string('noSuratPST', 150)->nullable();

            // Info lokasi & karakter TPU
            $table->string('lokasiSama', 100)->nullable();    // Iya/Tidak
            $table->string('namaTPU', 255)->nullable();
            $table->string('jenisTPU', 150)->nullable();
            $table->string('statusTanah', 150)->nullable();
            $table->json('karakterTPU')->nullable();          // list of strings

            $table->string('aksesJalan', 255)->nullable();
            $table->string('lokasiBerdekatan', 100)->nullable();

            // Alamat TPU
            $table->string('alamatTPU', 500)->nullable();
            $table->string('rtTPU', 10)->nullable();
            $table->string('rwTPU', 10)->nullable();
            $table->string('kecamatanTPU', 100)->nullable();
            $table->string('kelurahanTPU', 100)->nullable();
            $table->string('titikLokasi', 255)->nullable();   // lat,long

            // Dokumen (array UUID → json)
            $table->json('ktpPemohon')->nullable();
            $table->json('aktaPerusahaan')->nullable();
            $table->json('suratPermohonan')->nullable();
            $table->json('suratPernyataan')->nullable();
            $table->json('suratKeteranganDesa')->nullable();
            $table->json('suratIzinLingkungan')->nullable();
            $table->json('suratPelepasan')->nullable();
            $table->json('sertifikatHAT')->nullable();
            $table->json('pertekBPN')->nullable();
            $table->json('suratKeteranganLokasi')->nullable(); // max 2 UUID

            // Status verifikasi
            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable();

            // Nomor BAST TPU (diisi ketika proses selesai)
            $table->string('noBASTTPU', 255)->nullable();

            $table->timestamps();

            $table->index('status_verifikasi_usulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpu_serah_terimas');
    }
};

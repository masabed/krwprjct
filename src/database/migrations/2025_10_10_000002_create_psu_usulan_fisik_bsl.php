<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usulan_fisik_bsl', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // PK
            $table->uuid('id')->primary();

            // Keterangan permohonan
            $table->date('tanggalPermohonan');
            $table->string('nomorSuratPermohonan', 200);

            // Sumber usulan & data pemohon
            $table->string('sumberUsulan', 150);
            $table->string('namaAspirator', 200)->nullable();
            $table->string('noKontakAspirator', 100)->nullable();
            $table->string('namaPIC', 200)->nullable();
            $table->string('noKontakPIC', 100)->nullable();

            // Rincian usulan
            $table->string('jenisUsulan', 200);
            $table->text('uraianMasalah')->nullable();

            // Dimensi usulan/eksisting
            $table->string('luasTanahTersedia', 100)->nullable();
            $table->string('luasSarana', 100)->nullable();

            // Lokasi usulan
            $table->string('jenisBSL', 150);
            $table->string('alamatCPCL', 500);
            $table->string('rtCPCL', 10)->nullable();
            $table->string('rwCPCL', 10)->nullable();
            $table->string('titikLokasiUsulan', 255);

            // Keterangan lokasi BSL
            $table->uuid('perumahanId')->nullable()->index();
            $table->string('statusTanah', 255)->nullable();

            // Dokumen pendukung (JSON of UUIDs) — nullable
            $table->json('suratPermohonanUsulanFisik')->nullable();
            $table->json('proposalUsulanFisik')->nullable();
            $table->json('sertifikatStatusTanah')->nullable();
            $table->json('dokumentasiEksisting')->nullable();

            // Audit
            $table->string('user_id')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_fisik_bsl');
    }
};

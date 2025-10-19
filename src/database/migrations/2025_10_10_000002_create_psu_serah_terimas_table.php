<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psu_serah_terimas', function (Blueprint $table) {
            $table->uuid('id')->primary();                   // PK = UUID usulan
            $table->uuid('perumahanId')->index();            // referensi perumahan

            // Data pemohon/pengusul
            $table->string('tipePengaju', 100);
            $table->string('namaPengusul');
            $table->string('nikPengusul', 100);              // simpan string (bisa leading zero)
            $table->string('noKontak', 100);                 // string (bisa +62, dsb)
            $table->string('email', 255);

            // Developer
            $table->string('jenisDeveloper', 100);
            $table->string('namaDeveloper');
            $table->string('alamatDeveloper', 500);
            $table->string('rtDeveloper', 10);
            $table->string('rwDeveloper', 10);
            $table->string('noBASTPSU')->nullable();
            $table->string('noSuratPST')->nullable();

            // Administratif
            $table->date('tanggalPengusulan');
            $table->string('tahapanPenyerahan', 100);

            // jenisPSU: dari payload = array of strings → JSON nullable
            $table->json('jenisPSU')->nullable();
            

            $table->string('nomorSiteplan', 150);
            $table->date('tanggalSiteplan');

            // Luasan (string supaya bisa 12,34 dst)
            $table->string('luasKeseluruhan', 50);
            $table->string('luasRuangTerbangun', 50);
            $table->string('luasRuangTerbuka', 50);

            // FILE ARRAYS (json of UUIDs) — dibuat nullable
            $table->json('dokumenIzinBangunan')->nullable();
            $table->json('dokumenIzinPemanfaatan')->nullable();
            $table->json('dokumenKondisi')->nullable();
            $table->json('dokumenTeknis')->nullable();
            $table->json('ktpPengusul')->nullable();
            $table->json('aktaPerusahaan')->nullable();
            $table->json('suratPermohonanPenyerahan')->nullable();
            $table->json('dokumenSiteplan')->nullable();
            $table->json('salinanSertifikat')->nullable();
            

            // Verifikasi & audit
            $table->unsignedTinyInteger('status_verifikasi')->default(0); // 0..4
            $table->string('pesan_verifikasi', 512)->nullable();
            $table->string('user_id')->index();              // pembuat usulan

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psu_serah_terimas');
    }
};

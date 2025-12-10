<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rutilahus', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Info umum
            $table->string('jenisProgram', 150)->nullable()->index();

            $table->string('kecamatan',150)->index();
            $table->string('kelurahan',150)->index();
            $table->string('nama_CPCL',255);
            $table->string('nomorNIK',30);
            $table->string('nomorKK',30);
            $table->string('jumlahKeluarga',10);

            // Alamat
            $table->string('alamatDusun',500);
            $table->string('alamatRT',500);
            $table->string('alamatRW',500);

            // Data rumah
            $table->string('umur',10);
            $table->string('luasTanah',100);
            $table->string('luasBangunan',100);
            $table->string('pendidikanTerakhir',100);
            $table->string('pekerjaan',100);
            $table->string('besaranPenghasilan',50);
            $table->string('statusKepemilikanRumah',50);
            $table->string('asetRumahLain',100);
            $table->string('asetTanahLain',100);
            $table->string('sumberPenerangan',100);
            $table->string('bantuanPerumahan',100);
            $table->string('jenisKawasan',100);
            $table->string('jenisKelamin',100);

            // Kondisi bangunan (opsional)
            $table->string('kondisiPondasi',255)->nullable();
            $table->string('kondisiSloof',255)->nullable();
            $table->string('kondisiKolom',255)->nullable();
            $table->string('kondisiRingBalok',255)->nullable();
            $table->string('kondisiRangkaAtap',255)->nullable();
            $table->string('kondisiDinding',255)->nullable();
            $table->string('kondisiLantai',255)->nullable();
            $table->string('kondisiPenutupAtap',255)->nullable();
            $table->string('aksesAirMinum',100)->nullable();
            $table->string('aksesAirSanitasi',100)->nullable();
            $table->string('pesan_verifikasi', 512)->nullable(); 

            // FILE UUIDs -> JSON arrays (bisa >1 UUID per field)
            $table->json('fotoKTP')->nullable();
            $table->json('fotoSuratTanah')->nullable();
            $table->json('fotoRumah')->nullable();
            $table->json('fotoKK')->nullable();
            $table->json('dokumentasiSurvey')->nullable();

            // Verifikasi (0..4)
            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0)->comment('0..4');
            $table->index('status_verifikasi_usulan');

            // user id bertipe UUID
            $table->uuid('user_id')->index();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('rutilahus');
    }
};

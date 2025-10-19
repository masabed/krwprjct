<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perumahans_db', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // PK
            $table->uuid('id')->primary();

            // Info perumahan
            $table->string('namaPerumahan');
            $table->string('developerPerumahan');
            $table->string('tahunDibangun', 10);
            $table->string('luasPerumahan', 50);
            $table->string('jenisPerumahan', 100);
            $table->string('kecamatan', 150);
            $table->string('kelurahan', 150);
            $table->string('alamatPerumahan', 500);
            $table->string('rtPerumahan', 10);
            $table->string('rwPerumahan', 10);
            $table->string('titikLokasi', 255)->comment('lat,lng (string)');

            // Dokumen (JSON arrays & string)
            $table->json('foto_gerbang')->nullable();        // array UUID
            $table->json('fileSerahTerimaTPU')->nullable();  // array UUID           
            $table->string('bastTPU')->nullable();           // string biasa (nullable)

            // Status & catatan
            $table->unsignedTinyInteger('status_serah_terima')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perumahans_db');
    }
};

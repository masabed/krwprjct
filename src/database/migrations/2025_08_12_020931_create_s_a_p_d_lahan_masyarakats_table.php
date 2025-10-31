<?php
// database/migrations/2025_08_08_000002_create_usulan_lahan_masyarakat_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usulan_lahan_masyarakat', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('user_id')->index();

            $table->string('namaPemilikLahan');
            $table->string('ukuranLahan', 50);

            // RENAME: statusKepemilikan -> statusLegalitasTanah
            $table->string('statusLegalitasTanah', 100);

            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);
            $table->string('pesan_verifikasi', 512)->nullable();

            // alamat dipisah dusun/RT/RW
            $table->string('alamatDusun', 255);
            $table->string('alamatRT', 10);
            $table->string('alamatRW', 10);

            $table->string('kecamatan', 150);
            $table->string('kelurahan', 150);

            // koordinat string "lat,lng"
            $table->string('titikLokasi')->nullable();

            // File disimpan sebagai ARRAY UUID (JSON)
            $table->json('buktiKepemilikan')->nullable();
            $table->json('dokumenProposal')->nullable();
            // HAPUS: $table->json('dokumenDJPM')->nullable();
            $table->json('fotoLahan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_lahan_masyarakat');
    }
};

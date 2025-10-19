<?php
// database/migrations/2025_08_08_000001_create_usulan_sapds_individual_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usulan_sapds_individual', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Pemilik usulan
            $table->string('user_id')->index(); // bisa dijadikan FK ke users.id jika mau

            $table->string('namaCalonPenerima');
            $table->string('nikCalonPenerima', 20);
            $table->string('noKKCalonPenerima', 20);
            $table->text('alamatPenerima');
            $table->text('rwPenerima');
            $table->text('rtPenerima');
            $table->string('kecamatan');
            $table->string('kelurahan');
            $table->string('ukuranLahan')->nullable();
            $table->string('ketersedianSumber'); // "Tersedia" / "Tidak Tersedia"
            $table->string('titikLokasi')->nullable();

            // Status verifikasi usulan (0..n)
            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);

            // FILES (array UUID → json)
            $table->json('fotoLahan')->nullable(); // ex: ["uuid1","uuid2",...]
            $table->json('fotoRumah')->nullable(); // ex: ["uuid1","uuid2",...]

            // Pesan verifikasi (opsional)
            $table->string('pesan_verifikasi', 512)->nullable();

            $table->timestamps();

            // Opsional: index untuk query by user + order by created_at
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('usulan_sapds_individual');
    }
};

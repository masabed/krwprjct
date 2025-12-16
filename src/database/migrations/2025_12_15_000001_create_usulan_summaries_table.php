<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usulan_summaries', function (Blueprint $table) {
            // TIDAK ada id

            $table->string('uuid_usulan');
            $table->string('form');

            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);

            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('titik_lokasi')->nullable();

            $table->timestamps();

            // kunci unik untuk updateOrCreate
            $table->unique(['form', 'uuid_usulan']);

            $table->index('status_verifikasi_usulan');
            $table->index('kecamatan');
            $table->index('kelurahan');
            $table->index('form');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_summaries');
    }
};

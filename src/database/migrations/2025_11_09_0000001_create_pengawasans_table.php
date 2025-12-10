<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pengawasans', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // PK
            $table->uuid('id')->primary();

            // Relasi fleksibel (tanpa FK constraint)
            $table->uuid('uuidUsulan')->index();        // usulan yang diawasi
            $table->uuid('uuidPembangunan')->index();   // pembangunan terkait
            $table->uuid('pengawas')->index();          // user pengawas (UUID/ID)

            // Tanggal pengawasan (NEW)
            $table->date('tanggal_pengawasan')->nullable()->index();

            // List UUID file (final uploads)
            $table->json('foto')->nullable();           // array of UUID

            // Catatan
            $table->string('pesan_pengawasan', 255)->nullable();

            $table->timestamps();

            // Index gabungan untuk query umum
            $table->index(['uuidUsulan', 'uuidPembangunan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawasans');
    }
};

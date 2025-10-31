<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perencanaans', function (Blueprint $table) {
            $table->uuid('id')->primary();              // uuid (PK)
            $table->uuid('uuidUsulan')->index();        // rel ke usulan (wajib diinput user)
            $table->string('nilaiHPS', 255)->nullable();       // string biasa
            $table->string('catatanSurvey', 512)->nullable();  // string biasa
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perencanaans');
    }
};

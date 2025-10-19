<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psu_upload_temps', function (Blueprint $table) {
            $table->uuid('uuid')->primary();                 // PK = uuid
            $table->string('user_id')->index();              // simpan siapa yang upload (opsional)
            $table->string('file_path');                     // psu_temp/{uuid}.ext
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psu_upload_temps');
    }
};

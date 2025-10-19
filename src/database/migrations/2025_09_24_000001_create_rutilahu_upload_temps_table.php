<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rutilahu_upload_temps', function (Blueprint $table) {
            $table->uuid('uuid')->primary();     // pakai uuid sebagai PK
            $table->uuid('user_id')->index();    // pemilik (UUID)
            $table->string('file_path', 500);    // rutilahu_temp/<uuid>.<ext>
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('rutilahu_upload_temps');
    }
};

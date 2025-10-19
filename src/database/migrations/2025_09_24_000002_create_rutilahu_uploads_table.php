<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rutilahu_uploads', function (Blueprint $table) {
            $table->uuid('uuid')->primary();     // pakai uuid sebagai PK
            $table->uuid('user_id')->index();
            $table->string('file_path', 500);    // rutilahu_final/<uuid>.<ext>
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('rutilahu_uploads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('psu_uploads', function (Blueprint $table) {
            $table->uuid('uuid')->primary();                 // PK = uuid (sama dgn temp supaya bisa “promote”)
            $table->string('user_id')->index();
            $table->string('file_path');                     // psu_final/{uuid}.ext (atau nama unik)
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psu_uploads');
    }
};

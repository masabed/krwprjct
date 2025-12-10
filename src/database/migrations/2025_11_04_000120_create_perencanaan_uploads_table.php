<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perencanaan_uploads', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->uuid('uuid')->primary();
            // user_id disimpan sebagai UUID/CHAR(36), bukan bigint
            $table->uuid('user_id')->index();

            $table->string('file_path', 512);      // contoh: perencanaan_final/<uuid>.<ext>
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perencanaan_uploads');
    }
};

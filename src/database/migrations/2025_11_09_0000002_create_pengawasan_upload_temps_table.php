<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pengawasan_upload_temps', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->uuid('uuid')->primary();             // UUID file (temp)
            $table->string('user_id', 36)->index();      // <-- user_id bertipe UUID/string
            $table->string('file_path', 512);            // contoh: pengawasan_temp/<uuid>.<ext>
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawasan_upload_temps');
    }
};

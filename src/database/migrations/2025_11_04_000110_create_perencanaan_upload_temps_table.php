<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perencanaan_upload_temps', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // Primary key UUID
            $table->uuid('uuid')->primary();

            // USER ID bertipe UUID (char(36)), bukan integer
            $table->uuid('user_id')->index();

            // Metadata file
            $table->string('file_path', 512);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perencanaan_upload_temps');
    }
};

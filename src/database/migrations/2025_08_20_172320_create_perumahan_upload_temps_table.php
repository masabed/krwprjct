<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('perumahan_upload_temps', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->uuid('uuid')->primary();

            // If users.id is BIGINT, change to foreignId(...)
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perumahan_upload_temps');
    }
};

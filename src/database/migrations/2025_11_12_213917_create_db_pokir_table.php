<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('db_pokir', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama', 200);
            $table->string('telepon', 30)->nullable();
            $table->string('photo')->nullable(); // path file hasil upload
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_pokir');
    }
};

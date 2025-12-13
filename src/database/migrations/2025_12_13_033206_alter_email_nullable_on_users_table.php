<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ubah kolom email jadi nullable, tetap string (255) & unique index tetap jalan
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // rollback: jadikan NOT NULL lagi (hati-hati kalau sudah ada data email NULL)
            $table->string('email')->nullable(false)->change();
        });
    }
};

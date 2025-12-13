<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perumahans_db', function (Blueprint $table) {
            // Ubah dari boolean → unsignedTinyInteger (0–4)
            $table->unsignedTinyInteger('status_serah_terima')
                ->default(0)
                ->comment('0–4 status serah terima')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('perumahans_db', function (Blueprint $table) {
            // Balik lagi ke tipe boolean kalau di-rollback
            $table->boolean('status_serah_terima')
                ->default(false)
                ->change();
        });
    }
};

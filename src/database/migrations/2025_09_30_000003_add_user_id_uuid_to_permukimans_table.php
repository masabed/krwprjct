<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('permukimans', function (Blueprint $table) {
            // Tambah kolom UUID nullable
            $table->uuid('user_id')->nullable()->after('id');

            // FK ke users.id (diasumsikan bertipe UUID)
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permukimans', function (Blueprint $table) {
            // Hapus FK lalu kolom
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

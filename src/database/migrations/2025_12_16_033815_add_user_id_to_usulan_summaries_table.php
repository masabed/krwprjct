<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usulan_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('usulan_summaries', 'user_kecamatan')) {
                $table->string('user_kecamatan')->nullable()->after('user_id');
                $table->index('user_kecamatan');
            }
            if (!Schema::hasColumn('usulan_summaries', 'user_kelurahan')) {
                $table->string('user_kelurahan')->nullable()->after('user_kecamatan');
                $table->index('user_kelurahan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('usulan_summaries', 'user_kelurahan')) {
                $table->dropIndex(['user_kelurahan']);
                $table->dropColumn('user_kelurahan');
            }
            if (Schema::hasColumn('usulan_summaries', 'user_kecamatan')) {
                $table->dropIndex(['user_kecamatan']);
                $table->dropColumn('user_kecamatan');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permukimans', function (Blueprint $table) {
            if (Schema::hasColumn('permukimans', 'proposal_usulan')) {
                $table->dropColumn('proposal_usulan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('permukimans', function (Blueprint $table) {
            // Kalau di-rollback, tambahkan lagi sebagai JSON nullable
            if (!Schema::hasColumn('permukimans', 'proposal_usulan')) {
                $table->json('proposal_usulan')->nullable();
            }
        });
    }
};

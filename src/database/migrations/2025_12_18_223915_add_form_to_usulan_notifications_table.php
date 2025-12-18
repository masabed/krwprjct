<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usulan_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('usulan_notifications', 'form')) {
                $table->string('form', 50)->nullable()->index()->after('uuid_usulan');
            }
        });

        // Optional backfill (kalau usulan_summaries ada)
        if (Schema::hasTable('usulan_summaries') && Schema::hasColumn('usulan_summaries', 'form')) {
            DB::statement("
                UPDATE usulan_notifications n
                JOIN usulan_summaries s
                  ON s.uuid_usulan = n.uuid_usulan
                SET n.form = s.form
                WHERE n.form IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('usulan_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('usulan_notifications', 'form')) {
                $table->dropColumn('form');
            }
        });
    }
};

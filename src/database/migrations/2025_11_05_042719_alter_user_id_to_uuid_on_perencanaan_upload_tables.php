<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Kalau pakai doctrine/dbal, ini bisa pakai ->change()
        // composer require doctrine/dbal --dev

        // perencanaan_upload_temps
        if (Schema::hasColumn('perencanaan_upload_temps', 'user_id')) {
            // Lepas index jika ada (nama index bisa beda di env kamu)
            try { Schema::table('perencanaan_upload_temps', fn(Blueprint $t) => $t->dropIndex(['user_id'])); } catch (\Throwable $e) {}
            // Ubah tipe ke CHAR(36)
            DB::statement('ALTER TABLE perencanaan_upload_temps MODIFY user_id CHAR(36) NOT NULL');
            Schema::table('perencanaan_upload_temps', fn(Blueprint $t) => $t->index('user_id'));
        }

        // perencanaan_uploads
        if (Schema::hasColumn('perencanaan_uploads', 'user_id')) {
            try { Schema::table('perencanaan_uploads', fn(Blueprint $t) => $t->dropIndex(['user_id'])); } catch (\Throwable $e) {}
            DB::statement('ALTER TABLE perencanaan_uploads MODIFY user_id CHAR(36) NOT NULL');
            Schema::table('perencanaan_uploads', fn(Blueprint $t) => $t->index('user_id'));
        }
    }

    public function down(): void
    {
        // Rollback ke BIGINT (hanya kalau benar-benar perlu)
        DB::statement('ALTER TABLE perencanaan_upload_temps MODIFY user_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE perencanaan_uploads MODIFY user_id BIGINT UNSIGNED NOT NULL');
    }
};

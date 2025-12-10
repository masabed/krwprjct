<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('perencanaans', function (Blueprint $table) {
            // array UUID file final (JSON)
            $table->json('lembarKontrol')->nullable()->after('catatanSurvey');
        });
    }

    public function down(): void
    {
        Schema::table('perencanaans', function (Blueprint $table) {
            $table->dropColumn('lembarKontrol');
        });
    }
};

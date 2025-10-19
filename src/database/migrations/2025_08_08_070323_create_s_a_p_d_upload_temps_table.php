<?php
// database/migrations/2025_08_08_000002_create_sapd_upload_temps_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sapd_upload_temps', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_id'); // sesuaikan tipe user_id kamu
            $table->string('file_path'); // storage path: sapd_temp/xxx.pdf
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sapd_upload_temps');
    }
};

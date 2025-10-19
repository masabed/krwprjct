<?php
// database/migrations/2025_08_08_000003_create_sapd_uploads_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sapd_uploads', function (Blueprint $table) {
            $table->uuid('uuid')->primary(); // sama dengan uuid di temp (file id)
            $table->uuid('user_id');
            $table->string('file_path'); // storage path: sapd_final/xxx.pdf
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sapd_uploads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usulan_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('owner_user_id')->index();
            $table->uuid('uuid_usulan')->index();

            $table->unsignedTinyInteger('from_status')->nullable();
            $table->unsignedTinyInteger('to_status');

            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('owner_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            $table->index(['owner_user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_notifications');
    }
};

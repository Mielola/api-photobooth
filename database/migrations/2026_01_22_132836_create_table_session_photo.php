<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_session_photo', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('type', 16);
            $table->string('photo_path', 100);
            $table->foreignId('session_id')->constrained('table_session')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_session_photo');
    }
};

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
        Schema::create('table_frame', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('nama_frame', 100);
            $table->integer('jumlah_foto');
            $table->foreignId('acara_id')->constrained('table_acara')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_frame');
    }
};

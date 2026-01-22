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
        Schema::create('table_acara', function (Blueprint $table) {
            $table->id();
            $table->ulid('uid')->unique();
            $table->string('nama_acara', 255);
            $table->string('nama_pengantin', 255);
            $table->date('tanggal');
            $table->boolean('status')->default(false);
            $table->string('background', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_acara');
    }
};

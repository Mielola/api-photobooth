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
            $table->string('nama_pengantin_pria', 255);
            $table->string('nama_pengantin_wanita', 255);
            $table->date('tanggal');
            $table->enum('status', [
                'active',
                'maintenance',
                'inactive'
            ])->default('inactive');
            $table->string('background', 255)->nullable();
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

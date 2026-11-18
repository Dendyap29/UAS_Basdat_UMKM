<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_waktu', function (Blueprint $t) {
            $t->unsignedInteger('id_waktu')->primary(); // format yyyymmdd, contoh 20261019
            $t->date('tanggal')->unique();
            $t->string('nama_hari', 15);
            $t->unsignedTinyInteger('bulan');
            $t->string('nama_bulan', 15);
            $t->unsignedTinyInteger('kuartal');
            $t->unsignedSmallInteger('tahun');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_waktu');
    }
};

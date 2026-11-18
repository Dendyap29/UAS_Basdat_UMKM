<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_waktu', function (Blueprint $t) {
            $t->unsignedInteger('waktu_key')->primary(); // format YYYYMMDD
            $t->date('tanggal')->unique();
            $t->unsignedTinyInteger('hari');
            $t->string('nama_hari', 15);
            $t->unsignedTinyInteger('hari_dalam_minggu'); // 1 = Senin .. 7 = Minggu
            $t->boolean('is_akhir_pekan')->default(false);
            $t->unsignedTinyInteger('minggu_dalam_tahun');
            $t->unsignedTinyInteger('bulan');
            $t->string('nama_bulan', 15);
            $t->unsignedTinyInteger('kuartal');
            $t->unsignedTinyInteger('semester');
            $t->unsignedSmallInteger('tahun');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_waktu');
    }
};

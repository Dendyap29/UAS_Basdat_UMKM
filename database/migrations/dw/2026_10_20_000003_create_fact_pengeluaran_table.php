<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fact_pengeluaran', function (Blueprint $t) {
            $t->bigIncrements('pengeluaran_fact_id');

            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('jenis_pengeluaran_key');
            $t->unsignedInteger('staf_key');

            $t->string('id_pengeluaran')->unique();
            $t->decimal('nominal', 14, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('jenis_pengeluaran_key')->references('jenis_pengeluaran_key')->on('dim_jenis_pengeluaran');
            $t->foreign('staf_key')->references('staf_key')->on('dim_staf');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fact_pengeluaran');
    }
};

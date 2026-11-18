<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_promo', function (Blueprint $t) {
            $t->increments('promo_key');
            $t->string('id_promo')->unique();
            $t->string('nama_promo')->nullable();
            $t->decimal('persen_diskon', 5, 2)->nullable();
            $t->dateTime('tanggal_mulai')->nullable();
            $t->dateTime('tanggal_selesai')->nullable();
            $t->integer('durasi_hari')->nullable();
            $t->string('kelompok_diskon', 20)->nullable();
        });

        $dw = DB::connection('dw');
        if ($dw->getDriverName() === 'mysql') {
            $dw->statement("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        }

        $dw->table('dim_promo')->insert([
            'promo_key'       => 0,
            'id_promo'        => '-',
            'nama_promo'      => 'Tanpa promo',
            'persen_diskon'   => 0,
            'kelompok_diskon' => 'Tanpa promo',
        ]);
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_promo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fact_stok_snapshot', function (Blueprint $t) {
            $t->bigIncrements('stok_fact_id');

            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('produk_key');

            $t->integer('stok');
            $t->integer('stok_minimum');
            $t->boolean('is_low_stock')->default(false);

            $t->unique(['waktu_key', 'produk_key']);
            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('produk_key')->references('produk_key')->on('dim_produk');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fact_stok_snapshot');
    }
};

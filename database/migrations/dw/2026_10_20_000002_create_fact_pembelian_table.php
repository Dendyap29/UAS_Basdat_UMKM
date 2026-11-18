<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fact_pembelian', function (Blueprint $t) {
            $t->bigIncrements('pembelian_fact_id');

            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('produk_key');
            $t->unsignedInteger('supplier_key');
            $t->unsignedInteger('staf_key');

            $t->string('id_pembelian')->index();
            $t->string('id_detail_pembelian')->unique();

            $t->integer('jumlah');
            $t->decimal('harga_beli', 12, 2);
            $t->decimal('total_biaya', 14, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('produk_key')->references('produk_key')->on('dim_produk');
            $t->foreign('supplier_key')->references('supplier_key')->on('dim_supplier');
            $t->foreign('staf_key')->references('staf_key')->on('dim_staf');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fact_pembelian');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fact_penjualan', function (Blueprint $t) {
            $t->bigIncrements('penjualan_fact_id');

            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('produk_key');
            $t->unsignedInteger('customer_key');
            $t->unsignedInteger('staf_key');
            $t->unsignedInteger('promo_key');
            $t->unsignedInteger('transaksi_key');

            $t->string('id_penjualan')->index();
            $t->string('id_detail_penjualan')->unique();

            $t->integer('jumlah');
            $t->decimal('harga_normal', 12, 2);
            $t->decimal('harga_satuan', 12, 2);
            $t->decimal('diskon_promo', 14, 2);
            $t->decimal('subtotal', 14, 2);
            $t->decimal('diskon_poin', 14, 2);
            $t->decimal('pendapatan_bersih', 14, 2);
            $t->decimal('hpp', 14, 2);
            $t->decimal('laba_kotor', 14, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('produk_key')->references('produk_key')->on('dim_produk');
            $t->foreign('customer_key')->references('customer_key')->on('dim_customer');
            $t->foreign('staf_key')->references('staf_key')->on('dim_staf');
            $t->foreign('promo_key')->references('promo_key')->on('dim_promo');
            $t->foreign('transaksi_key')->references('transaksi_key')->on('dim_transaksi');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fact_penjualan');
    }
};

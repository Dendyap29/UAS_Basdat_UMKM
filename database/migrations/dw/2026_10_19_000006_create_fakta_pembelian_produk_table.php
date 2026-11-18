<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fakta_pembelian_produk', function (Blueprint $t) {
            $t->id('sk_pembelian');
            $t->string('id_detail_pembelian', 20)->unique(); // kunci dari database lama
            $t->string('no_pembelian', 20);                   // nomor transaksi, contoh PB0001

            $t->unsignedInteger('id_waktu');
            $t->unsignedBigInteger('sk_supplier');
            $t->unsignedBigInteger('sk_produk');
            $t->unsignedBigInteger('sk_varian');
            $t->unsignedBigInteger('sk_staf');

            $t->unsignedInteger('jumlah_dibeli');
            $t->decimal('harga_satuan', 12, 2);
            $t->decimal('total_biaya_pembelian', 14, 2);

            $t->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $t->foreign('sk_supplier')->references('sk_supplier')->on('dim_supplier');
            $t->foreign('sk_produk')->references('sk_produk')->on('dim_produk');
            $t->foreign('sk_varian')->references('sk_varian')->on('dim_varian_produk');
            $t->foreign('sk_staf')->references('sk_staf')->on('dim_staf');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fakta_pembelian_produk');
    }
};

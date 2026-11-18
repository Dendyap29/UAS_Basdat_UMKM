<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grain: satu baris = satu baris detail_penjualan
     * (sama seperti fakta_pembelian_produk yang berbasis detail_pembelian).
     */
    public function up(): void
    {
        Schema::connection('dw')->create('fakta_penjualan', function (Blueprint $t) {
            $t->id('sk_penjualan');
            $t->string('id_detail_penjualan', 20)->unique(); // kunci dari database lama
            $t->string('no_penjualan', 20)->index();         // degenerate dimension, contoh PJ00001

            $t->unsignedInteger('id_waktu');
            $t->unsignedBigInteger('sk_produk');
            $t->unsignedBigInteger('sk_varian');
            $t->unsignedBigInteger('sk_pelanggan')->nullable(); // NULL = pembeli offline non-member
            $t->unsignedBigInteger('sk_staf')->nullable();      // NULL = transaksi online (tanpa kasir)

            $t->string('kanal_penjualan', 10);    // ONLINE / OFFLINE
            $t->string('metode_pembayaran', 10);  // QR / TUNAI

            $t->unsignedInteger('jumlah_terjual');
            $t->decimal('harga_satuan', 12, 2);        // harga setelah promo
            $t->decimal('subtotal_item', 14, 2);       // jumlah x harga_satuan
            $t->decimal('diskon_poin_alokasi', 14, 2)->default(0); // diskon poin transaksi, dibagi proporsional
            $t->decimal('total_bersih_item', 14, 2);   // subtotal_item - diskon_poin_alokasi

            $t->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $t->foreign('sk_produk')->references('sk_produk')->on('dim_produk');
            $t->foreign('sk_varian')->references('sk_varian')->on('dim_varian_produk');
            $t->foreign('sk_pelanggan')->references('sk_pelanggan')->on('dim_pelanggan');
            $t->foreign('sk_staf')->references('sk_staf')->on('dim_staf');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fakta_penjualan');
    }
};

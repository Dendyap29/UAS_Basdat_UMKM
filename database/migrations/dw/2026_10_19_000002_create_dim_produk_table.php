<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_produk', function (Blueprint $t) {
            $t->increments('produk_key');
            $t->string('id_varian')->unique();
            $t->string('ukuran', 10);
            $t->string('warna', 50);
            $t->unsignedInteger('stok_minimum')->default(0);
            $t->string('id_produk');
            $t->string('nama_produk');
            $t->decimal('harga_jual', 12, 2);
            $t->string('status_produk', 10);
            $t->string('id_kategori');
            $t->string('nama_kategori');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_produk');
    }
};

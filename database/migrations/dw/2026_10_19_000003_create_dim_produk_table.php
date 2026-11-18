<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_produk', function (Blueprint $t) {
            $t->id('sk_produk');
            $t->string('id_produk', 20)->unique();
            $t->string('nama_produk');
            $t->string('kategori');
            $t->decimal('harga_jual', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_produk');
    }
};

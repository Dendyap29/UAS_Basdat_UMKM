<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('fakta_pengeluaran', function (Blueprint $t) {
            $t->id('sk_pengeluaran');
            $t->string('id_pengeluaran', 20)->unique();
            $t->string('id_pembelian', 20)->nullable();

            $t->unsignedInteger('id_waktu');
            $t->unsignedBigInteger('sk_staf');

            $t->string('kategori_biaya', 25);     // OPERASIONAL / RESTOCK_PRODUK
            $t->string('jenis_pengeluaran', 30);  // LISTRIK, INTERNET, SEWA, ONGKIR, PERAWATAN, LAINNYA, PEMBELIAN_PRODUK
            $t->decimal('nominal_pengeluaran', 14, 2);

            $t->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $t->foreign('sk_staf')->references('sk_staf')->on('dim_staf');
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('fakta_pengeluaran');
    }
};

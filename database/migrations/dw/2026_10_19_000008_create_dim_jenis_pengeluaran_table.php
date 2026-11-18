<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_jenis_pengeluaran', function (Blueprint $t) {
            $t->unsignedInteger('jenis_pengeluaran_key')->primary();
            $t->string('jenis_pengeluaran', 30)->unique();
            $t->string('kelompok', 30);
        });

        DB::connection('dw')->table('dim_jenis_pengeluaran')->insert([
            ['jenis_pengeluaran_key' => 1, 'jenis_pengeluaran' => 'PEMBELIAN_PRODUK', 'kelompok' => 'PEMBELIAN STOK'],
            ['jenis_pengeluaran_key' => 2, 'jenis_pengeluaran' => 'LISTRIK', 'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 3, 'jenis_pengeluaran' => 'INTERNET', 'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 4, 'jenis_pengeluaran' => 'SEWA', 'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 5, 'jenis_pengeluaran' => 'ONGKIR', 'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 6, 'jenis_pengeluaran' => 'PERAWATAN', 'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 7, 'jenis_pengeluaran' => 'LAINNYA', 'kelompok' => 'OPERASIONAL'],
        ]);
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_jenis_pengeluaran');
    }
};

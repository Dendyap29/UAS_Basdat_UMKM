<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_transaksi', function (Blueprint $t) {
            $t->unsignedInteger('transaksi_key')->primary();
            $t->string('kanal_penjualan', 10);
            $t->string('metode_pembayaran', 10);

            $t->unique(['kanal_penjualan', 'metode_pembayaran']);
        });

        DB::connection('dw')->table('dim_transaksi')->insert([
            ['transaksi_key' => 1, 'kanal_penjualan' => 'ONLINE', 'metode_pembayaran' => 'QR'],
            ['transaksi_key' => 2, 'kanal_penjualan' => 'ONLINE', 'metode_pembayaran' => 'TUNAI'],
            ['transaksi_key' => 3, 'kanal_penjualan' => 'OFFLINE', 'metode_pembayaran' => 'QR'],
            ['transaksi_key' => 4, 'kanal_penjualan' => 'OFFLINE', 'metode_pembayaran' => 'TUNAI'],
        ]);
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_transaksi');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian_produk', function (Blueprint $table) {
            $table->dateTime('tanggal_diterima')->nullable()->after('tanggal_pembelian');
        });

        DB::table('pembelian_produk')
            ->where('status_pembelian', 'DITERIMA')
            ->whereNull('tanggal_diterima')
            ->update(['tanggal_diterima' => DB::raw('updated_at')]);

        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id('id_mutasi');
            $table->string('id_varian');
            $table->dateTime('tanggal');
            $table->enum('jenis', ['MASUK', 'KELUAR', 'PENYESUAIAN']);
            $table->integer('jumlah');
            $table->unsignedInteger('stok_sesudah');
            $table->string('referensi')->nullable();
            $table->timestamps();

            $table->foreign('id_varian')
                ->references('id_varian')
                ->on('varian_produk')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index(['id_varian', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');

        Schema::table('pembelian_produk', function (Blueprint $table) {
            $table->dropColumn('tanggal_diterima');
        });
    }
};

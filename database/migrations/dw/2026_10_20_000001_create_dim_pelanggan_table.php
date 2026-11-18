<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_pelanggan', function (Blueprint $t) {
            $t->id('sk_pelanggan');
            $t->string('id_akun', 20)->unique();   // kunci dari database lama (akun CUSTOMER)
            $t->string('nama_pelanggan');
            $t->date('tanggal_daftar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_pelanggan');
    }
};

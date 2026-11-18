<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_varian_produk', function (Blueprint $t) {
            $t->id('sk_varian');
            $t->string('id_varian', 20)->unique();
            $t->string('ukuran', 10);
            $t->string('warna', 50);
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_varian_produk');
    }
};

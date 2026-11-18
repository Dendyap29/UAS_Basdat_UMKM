<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_supplier', function (Blueprint $t) {
            $t->id('sk_supplier');
            $t->string('id_supplier', 20)->unique();
            $t->string('nama_supplier');
            $t->string('kota_supplier')->nullable();
            $t->string('no_telp', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_supplier');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_supplier', function (Blueprint $t) {
            $t->increments('supplier_key');
            $t->string('id_supplier')->unique();
            $t->string('nama_supplier')->nullable();
            $t->text('alamat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_supplier');
    }
};

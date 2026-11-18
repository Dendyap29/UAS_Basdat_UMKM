<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_staf', function (Blueprint $t) {
            $t->id('sk_staf');
            $t->string('id_akun', 20)->unique();
            $t->string('nama_staf');
            $t->string('tipe_akun', 20);
            $t->string('email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_staf');
    }
};

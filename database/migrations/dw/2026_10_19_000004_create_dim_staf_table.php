<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('dw')->create('dim_staf', function (Blueprint $t) {
            $t->increments('staf_key');
            $t->string('id_akun')->unique();
            $t->string('nama')->nullable();
            $t->string('peran', 10)->nullable();
            $t->string('status_akun', 10)->nullable();
        });

        $dw = DB::connection('dw');
        if ($dw->getDriverName() === 'mysql') {
            $dw->statement("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        }

        $dw->table('dim_staf')->insert([
            'staf_key' => 0,
            'id_akun'  => '-',
            'nama'     => 'Tidak ada (online)',
            'peran'    => '-',
        ]);
    }

    public function down(): void
    {
        Schema::connection('dw')->dropIfExists('dim_staf');
    }
};

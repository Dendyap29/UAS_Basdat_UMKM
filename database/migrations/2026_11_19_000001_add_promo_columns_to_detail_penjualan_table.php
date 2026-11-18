<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_penjualan', function (Blueprint $table) {
            $table->decimal('harga_normal', 12, 2)->default(0)->after('jumlah');
            $table->decimal('persen_diskon', 5, 2)->default(0)->after('harga_satuan');
            $table->string('id_promo')->nullable()->after('persen_diskon');

            $table->foreign('id_promo')
                ->references('id_promo')
                ->on('promo')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Backfill existing detail_penjualan rows safely across MySQL/SQLite
        $details = DB::table('detail_penjualan as d')
            ->join('varian_produk as v', 'd.id_varian', '=', 'v.id_varian')
            ->join('produk as p', 'v.id_produk', '=', 'p.id_produk')
            ->select('d.id_detail_penjualan', 'd.harga_satuan', 'p.harga_jual')
            ->get();

        foreach ($details as $row) {
            $normal = (float) $row->harga_jual;
            $unit = (float) $row->harga_satuan;
            $pct = ($normal > 0 && $unit < $normal)
                ? round((($normal - $unit) / $normal) * 100, 2)
                : 0.0;

            DB::table('detail_penjualan')
                ->where('id_detail_penjualan', $row->id_detail_penjualan)
                ->update([
                    'harga_normal' => $normal,
                    'persen_diskon' => $pct,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('detail_penjualan', function (Blueprint $table) {
            $table->dropForeign(['id_promo']);
            $table->dropColumn(['harga_normal', 'persen_diskon', 'id_promo']);
        });
    }
};

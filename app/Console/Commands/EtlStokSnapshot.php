<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlStokSnapshot extends Command
{
    protected $signature = 'dw:etl-stok {--skip-dims : Lewati pembaruan tabel dimensi}';

    protected $description = 'Ambil periodic snapshot stok varian produk saat ini ke fact_stok_snapshot di umkm_dw';

    public function handle(): int
    {
        if (!$this->option('skip-dims')) {
            $this->call('dw:etl-dimensi');
        }

        $src = DB::connection();
        $dw  = DB::connection('dw');

        $todayKey  = (int) now()->format('Ymd');
        $mapProduk = $dw->table('dim_produk')->pluck('produk_key', 'id_varian')->all();

        $variants = $src->table('varian_produk')->orderBy('id_varian')->get();
        $rows = [];

        foreach ($variants as $v) {
            $produkKey = $mapProduk[$v->id_varian] ?? null;
            if ($produkKey === null) {
                continue;
            }

            $rows[] = [
                'waktu_key'    => $todayKey,
                'produk_key'   => $produkKey,
                'stok'         => (int) $v->stok,
                'stok_minimum' => (int) $v->stok_minimum,
                'is_low_stock' => ((int) $v->stok <= (int) $v->stok_minimum) ? 1 : 0,
            ];
        }

        $dw->transaction(function () use ($dw, $rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                $dw->table('fact_stok_snapshot')->upsert(
                    $chunk,
                    ['waktu_key', 'produk_key'],
                    ['stok', 'stok_minimum', 'is_low_stock']
                );
            }
        });

        $this->info('Selesai. '.count($rows).' snapshot varian masuk ke fact_stok_snapshot.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrunesStaleFacts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPembelian extends Command
{
    use PrunesStaleFacts;

    protected $signature = 'dw:etl-pembelian {--skip-dims : Lewati pembaruan tabel dimensi}';

    protected $description = 'Salin data pembelian DITERIMA dari database OLTP ke fact_pembelian di umkm_dw';

    public function handle(): int
    {
        if (!$this->option('skip-dims')) {
            $this->call('dw:etl-dimensi');
        }

        $src = DB::connection();
        $dw  = DB::connection('dw');

        $this->info('Mengekstrak & mentransformasi fact_pembelian...');
        $mapProduk   = $dw->table('dim_produk')->pluck('produk_key', 'id_varian')->all();
        $mapSupplier = $dw->table('dim_supplier')->pluck('supplier_key', 'id_supplier')->all();
        $mapStaf     = $dw->table('dim_staf')->pluck('staf_key', 'id_akun')->all();

        $rows = $src->table('detail_pembelian as d')
            ->join('pembelian_produk as p', 'd.id_pembelian', '=', 'p.id_pembelian')
            ->where('p.status_pembelian', 'DITERIMA')
            ->select(
                'd.id_detail_pembelian',
                'd.id_pembelian',
                'd.id_varian',
                'd.jumlah',
                'd.harga_satuan',
                'p.id_supplier',
                'p.id_akun',
                'p.tanggal_pembelian',
                'p.tanggal_diterima'
            )
            ->orderBy('d.id_detail_pembelian')
            ->get();

        $factRows = [];
        $dilewati = 0;

        foreach ($rows as $r) {
            $produkKey   = $mapProduk[$r->id_varian] ?? null;
            $supplierKey = $mapSupplier[$r->id_supplier] ?? null;
            $stafKey     = $mapStaf[$r->id_akun] ?? null;

            if ($produkKey === null || $supplierKey === null || $stafKey === null) {
                $dilewati++;
                continue;
            }

            $tglBasis = $r->tanggal_diterima ?: $r->tanggal_pembelian;

            $factRows[] = [
                'waktu_key'           => (int) Carbon::parse($tglBasis)->format('Ymd'),
                'produk_key'          => $produkKey,
                'supplier_key'        => $supplierKey,
                'staf_key'            => $stafKey,
                'id_pembelian'        => $r->id_pembelian,
                'id_detail_pembelian' => $r->id_detail_pembelian,
                'jumlah'              => (int) $r->jumlah,
                'harga_beli'          => (float) $r->harga_satuan,
                'total_biaya'         => round((int) $r->jumlah * (float) $r->harga_satuan, 2),
            ];
        }

        $dw->transaction(function () use ($dw, $factRows) {
            foreach (array_chunk($factRows, 50) as $chunk) {
                $dw->table('fact_pembelian')->upsert(
                    $chunk,
                    ['id_detail_pembelian'],
                    ['waktu_key', 'produk_key', 'supplier_key', 'staf_key', 'id_pembelian', 'jumlah', 'harga_beli', 'total_biaya']
                );
            }
        });

        $dihapus = $this->pruneStale($dw, 'fact_pembelian', 'id_detail_pembelian', array_column($factRows, 'id_detail_pembelian'));

        $dimuat = count($factRows);
        $this->info("Selesai. {$dimuat} baris masuk ke fact_pembelian, {$dilewati} dilewati, {$dihapus} baris basi dihapus.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrunesStaleFacts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPenjualan extends Command
{
    use PrunesStaleFacts;

    protected $signature = 'dw:etl-penjualan {--skip-dims : Lewati pembaruan tabel dimensi}';

    protected $description = 'Salin data penjualan valid dari database OLTP ke fact_penjualan di umkm_dw';

    public function handle(): int
    {
        if (!$this->option('skip-dims')) {
            $this->call('dw:etl-dimensi');
        }

        $src = DB::connection();
        $dw  = DB::connection('dw');

        $this->info('Menyiapkan kamus surrogate key & HPP varian...');
        $mapProduk    = $dw->table('dim_produk')->pluck('produk_key', 'id_varian')->all();
        $mapCustomer  = $dw->table('dim_customer')->pluck('customer_key', 'id_akun')->all();
        $mapStaf      = $dw->table('dim_staf')->pluck('staf_key', 'id_akun')->all();
        $mapPromo     = $dw->table('dim_promo')->pluck('promo_key', 'id_promo')->all();
        $mapTransaksi = $dw->table('dim_transaksi')
            ->get()
            ->mapWithKeys(fn ($t) => ["{$t->kanal_penjualan}|{$t->metode_pembayaran}" => $t->transaksi_key])
            ->all();

        // HPP rata-rata tertimbang per varian dari pembelian DITERIMA
        $hppMap = $src->table('detail_pembelian as dp')
            ->join('pembelian_produk as pb', 'dp.id_pembelian', '=', 'pb.id_pembelian')
            ->where('pb.status_pembelian', 'DITERIMA')
            ->selectRaw('dp.id_varian, SUM(dp.jumlah * dp.harga_satuan) / NULLIF(SUM(dp.jumlah), 0) as hpp_unit')
            ->groupBy('dp.id_varian')
            ->pluck('hpp_unit', 'dp.id_varian')
            ->all();

        $this->info('Mengekstrak & mentransformasi fact_penjualan...');
        $penjualan = $src->table('detail_penjualan as d')
            ->join('penjualan as p', 'd.id_penjualan', '=', 'p.id_penjualan')
            ->join('varian_produk as v', 'd.id_varian', '=', 'v.id_varian')
            ->join('produk as pr', 'v.id_produk', '=', 'pr.id_produk')
            ->leftJoin('penjualan_member as pm', 'p.id_penjualan', '=', 'pm.id_penjualan')
            ->where('p.status_pembayaran', 'BERHASIL')
            ->where('p.status_penjualan', '!=', 'BATAL')
            ->orderBy('d.id_penjualan')
            ->orderBy('d.id_detail_penjualan')
            ->select(
                'd.id_detail_penjualan',
                'd.id_penjualan',
                'd.id_varian',
                'd.jumlah',
                'd.harga_normal',
                'd.harga_satuan',
                'd.id_promo',
                'pr.harga_jual as produk_harga_jual',
                'p.id_akun',
                'p.tanggal_penjualan',
                'p.kanal_penjualan',
                'p.metode_pembayaran',
                'p.nominal_bayar',
                'p.diskon_poin',
                'pm.id_akun_customer'
            )
            ->get()
            ->groupBy('id_penjualan');

        $factRows = [];
        $transaksiMasuk = 0;
        $dilewati = 0;
        $takSinkron = [];

        foreach ($penjualan as $idPenjualan => $baris) {
            $h = $baris->first();

            if ($h->kanal_penjualan === 'ONLINE') {
                $customerKey = $mapCustomer[$h->id_akun] ?? null;
                $stafKey     = 0;
            } else {
                $customerKey = $h->id_akun_customer
                    ? ($mapCustomer[$h->id_akun_customer] ?? null)
                    : 0;
                $stafKey = $mapStaf[$h->id_akun] ?? null;
            }

            $transaksiKey = $mapTransaksi["{$h->kanal_penjualan}|{$h->metode_pembayaran}"] ?? null;
            $waktuKey     = (int) Carbon::parse($h->tanggal_penjualan)->format('Ymd');

            $lengkap = $customerKey !== null
                && $stafKey !== null
                && $transaksiKey !== null
                && $baris->every(fn ($r) => isset($mapProduk[$r->id_varian]));

            if (!$lengkap) {
                $dilewati++;
                continue;
            }

            $subtotalTransaksi = $baris->sum(fn ($r) => $r->jumlah * $r->harga_satuan);
            $diskonPoinTotal   = (float) $h->diskon_poin;
            $sisaDiskon        = $diskonPoinTotal;
            $jumlahBaris       = $baris->count();
            $urutan            = 0;
            $totalBersih       = 0.0;

            foreach ($baris as $r) {
                $urutan++;
                $jumlah      = (int) $r->jumlah;
                $hargaSatuan = (float) $r->harga_satuan;
                $hargaNormal = (float) ($r->harga_normal > 0 ? $r->harga_normal : $r->produk_harga_jual);
                $subtotal    = round($jumlah * $hargaSatuan, 2);
                $diskonPromo = round(max(0, ($hargaNormal - $hargaSatuan) * $jumlah), 2);

                if ($diskonPoinTotal <= 0 || $subtotalTransaksi <= 0) {
                    $alokasiPoin = 0.0;
                } elseif ($urutan === $jumlahBaris) {
                    $alokasiPoin = round($sisaDiskon, 2);
                } else {
                    $alokasiPoin = round($diskonPoinTotal * $subtotal / $subtotalTransaksi, 2);
                    $sisaDiskon -= $alokasiPoin;
                }

                $pendapatanBersih = round($subtotal - $alokasiPoin, 2);
                $totalBersih += $pendapatanBersih;

                $hppUnit   = (float) ($hppMap[$r->id_varian] ?? 0);
                $hpp       = round($hppUnit * $jumlah, 2);
                $labaKotor = round($pendapatanBersih - $hpp, 2);
                $promoKey  = $r->id_promo ? ($mapPromo[$r->id_promo] ?? 0) : 0;

                $factRows[] = [
                    'waktu_key'           => $waktuKey,
                    'produk_key'          => $mapProduk[$r->id_varian],
                    'customer_key'        => $customerKey,
                    'staf_key'            => $stafKey,
                    'promo_key'           => $promoKey,
                    'transaksi_key'       => $transaksiKey,
                    'id_penjualan'        => $idPenjualan,
                    'id_detail_penjualan' => $r->id_detail_penjualan,
                    'jumlah'              => $jumlah,
                    'harga_normal'        => $hargaNormal,
                    'harga_satuan'        => $hargaSatuan,
                    'diskon_promo'        => $diskonPromo,
                    'subtotal'            => $subtotal,
                    'diskon_poin'         => $alokasiPoin,
                    'pendapatan_bersih'   => $pendapatanBersih,
                    'hpp'                 => $hpp,
                    'laba_kotor'          => $labaKotor,
                ];
            }

            $transaksiMasuk++;

            if (abs($totalBersih - (float) $h->nominal_bayar) > 0.01) {
                $takSinkron[] = sprintf(
                    '%s (DW %s vs OLTP %s)',
                    $idPenjualan,
                    number_format($totalBersih, 2, '.', ''),
                    number_format((float) $h->nominal_bayar, 2, '.', '')
                );
            }
        }

        $dw->transaction(function () use ($dw, $factRows) {
            foreach (array_chunk($factRows, 50) as $chunk) {
                $dw->table('fact_penjualan')->upsert(
                    $chunk,
                    ['id_detail_penjualan'],
                    [
                        'waktu_key', 'produk_key', 'customer_key', 'staf_key',
                        'promo_key', 'transaksi_key', 'id_penjualan', 'jumlah',
                        'harga_normal', 'harga_satuan', 'diskon_promo', 'subtotal',
                        'diskon_poin', 'pendapatan_bersih', 'hpp', 'laba_kotor',
                    ]
                );
            }
        });

        $dihapus = $this->pruneStale($dw, 'fact_penjualan', 'id_detail_penjualan', array_column($factRows, 'id_detail_penjualan'));

        if ($takSinkron) {
            $this->warn(count($takSinkron).' transaksi selisih dengan nominal_bayar sumber.');
        }

        $barisMasuk = count($factRows);
        $this->info("Selesai. {$transaksiMasuk} transaksi ({$barisMasuk} baris) masuk ke fact_penjualan, {$dilewati} dilewati, {$dihapus} baris basi dihapus.");

        return self::SUCCESS;
    }
}

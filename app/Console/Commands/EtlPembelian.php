<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPembelian extends Command
{
    protected $signature = 'dw:etl-pembelian';

    protected $description = 'Salin data pembelian dari database umkm_pakaian ke DW 2 (umkm_dw)';

    public function handle(): int
    {
        $src = DB::connection();      // database lama (umkm_pakaian)
        $dw  = DB::connection('dw');  // database DW (umkm_dw)

        // ---------- 1. Dim_Supplier ----------
        $this->info('1/6 Mengisi dim_supplier...');
        foreach ($src->table('supplier')->get() as $s) {
            $dw->table('dim_supplier')->updateOrInsert(
                ['id_supplier' => $s->id_supplier],
                [
                    'nama_supplier' => $s->nama_supplier,
                    'kota_supplier' => $s->alamat,
                    'no_telp'       => $s->no_telp,
                ]
            );
        }

        // ---------- 2. Dim_Produk (digabung dengan kategori) ----------
        $this->info('2/6 Mengisi dim_produk...');
        $produk = $src->table('produk as p')
            ->join('kategori_produk as k', 'p.id_kategori', '=', 'k.id_kategori')
            ->select('p.id_produk', 'p.nama_produk', 'p.harga_jual', 'k.nama_kategori')
            ->get();
        foreach ($produk as $p) {
            $dw->table('dim_produk')->updateOrInsert(
                ['id_produk' => $p->id_produk],
                [
                    'nama_produk' => $p->nama_produk,
                    'kategori'    => $p->nama_kategori,
                    'harga_jual'  => $p->harga_jual,
                ]
            );
        }

        // ---------- 3. Dim_Varian_Produk ----------
        $this->info('3/6 Mengisi dim_varian_produk...');
        foreach ($src->table('varian_produk')->get() as $v) {
            $dw->table('dim_varian_produk')->updateOrInsert(
                ['id_varian' => $v->id_varian],
                ['ukuran' => $v->ukuran, 'warna' => $v->warna]
            );
        }

        // ---------- 4. Dim_Staf (hanya ADMIN dan KASIR) ----------
        $this->info('4/6 Mengisi dim_staf...');
        $staf = $src->table('akun')->whereIn('tipe_akun', ['ADMIN', 'KASIR'])->get();
        foreach ($staf as $a) {
            $dw->table('dim_staf')->updateOrInsert(
                ['id_akun' => $a->id_akun],
                [
                    'nama_staf' => $a->nama,
                    'tipe_akun' => $a->tipe_akun,
                    'email'     => $a->email,
                ]
            );
        }

        // ---------- 5. Dim_Waktu (dari tanggal pembelian yang ada) ----------
        $this->info('5/6 Mengisi dim_waktu...');
        $tanggal = $src->table('pembelian_produk')
            ->selectRaw('DATE(tanggal_pembelian) as tgl')
            ->distinct()
            ->pluck('tgl');
        foreach ($tanggal as $tgl) {
            $d = Carbon::parse($tgl)->locale('id');
            $dw->table('dim_waktu')->updateOrInsert(
                ['id_waktu' => (int) $d->format('Ymd')],
                [
                    'tanggal'    => $d->toDateString(),
                    'nama_hari'  => $d->translatedFormat('l'),
                    'bulan'      => $d->month,
                    'nama_bulan' => $d->translatedFormat('F'),
                    'kuartal'    => $d->quarter,
                    'tahun'      => $d->year,
                ]
            );
        }

        // ---------- 6. Fakta ----------
        $this->info('6/6 Mengisi fakta_pembelian_produk...');

        // Kamus: ID lama -> kunci baru (sk_...) di DW
        $mapSupplier = $dw->table('dim_supplier')->pluck('sk_supplier', 'id_supplier')->all();
        $mapProduk   = $dw->table('dim_produk')->pluck('sk_produk', 'id_produk')->all();
        $mapVarian   = $dw->table('dim_varian_produk')->pluck('sk_varian', 'id_varian')->all();
        $mapStaf     = $dw->table('dim_staf')->pluck('sk_staf', 'id_akun')->all();

        $rows = $src->table('detail_pembelian as d')
            ->join('pembelian_produk as p', 'd.id_pembelian', '=', 'p.id_pembelian')
            ->join('varian_produk as v', 'd.id_varian', '=', 'v.id_varian')
            ->where('p.status_pembelian', 'DITERIMA') // hanya barang yang sudah masuk
            ->select(
                'd.id_detail_pembelian',
                'd.id_pembelian',
                'd.id_varian',
                'd.jumlah',
                'd.harga_satuan',
                'p.id_supplier',
                'p.id_akun',
                'p.tanggal_pembelian',
                'v.id_produk'
            )
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Belum ada pembelian berstatus DITERIMA, jadi tabel fakta masih kosong.');
        }

        $dimuat = 0;
        foreach ($rows as $r) {
            $skSupplier = $mapSupplier[$r->id_supplier] ?? null;
            $skProduk   = $mapProduk[$r->id_produk] ?? null;
            $skVarian   = $mapVarian[$r->id_varian] ?? null;
            $skStaf     = $mapStaf[$r->id_akun] ?? null;

            if (!$skSupplier || !$skProduk || !$skVarian || !$skStaf) {
                $this->warn("Dilewati {$r->id_detail_pembelian}: data dimensinya tidak lengkap.");
                continue;
            }

            $dw->table('fakta_pembelian_produk')->updateOrInsert(
                ['id_detail_pembelian' => $r->id_detail_pembelian],
                [
                    'no_pembelian'          => $r->id_pembelian,
                    'id_waktu'              => (int) Carbon::parse($r->tanggal_pembelian)->format('Ymd'),
                    'sk_supplier'           => $skSupplier,
                    'sk_produk'             => $skProduk,
                    'sk_varian'             => $skVarian,
                    'sk_staf'               => $skStaf,
                    'jumlah_dibeli'         => $r->jumlah,
                    'harga_satuan'          => $r->harga_satuan,
                    'total_biaya_pembelian' => round($r->jumlah * $r->harga_satuan, 2),
                ]
            );
            $dimuat++;
        }

        $this->info("Selesai. {$dimuat} baris masuk ke tabel fakta.");

        return self::SUCCESS;
    }
}

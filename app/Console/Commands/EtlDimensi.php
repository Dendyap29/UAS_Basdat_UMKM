<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlDimensi extends Command
{
    protected $signature = 'dw:etl-dimensi';

    protected $description = 'Isi dan perbarui seluruh tabel dimensi (dim_*) pada Data Warehouse umkm_dw';

    private function upsertChunked($dw, string $table, array $rows, array $uniqueBy, array $updateColumns, int $chunkSize = 100): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $dw->table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }

    public function handle(): int
    {
        $src = DB::connection();
        $dw  = DB::connection('dw');

        // ---------- 0. Pastikan baris khusus key 0 dan dimensi statis tersedia ----------
        if ($dw->getDriverName() === 'mysql') {
            $dw->statement("SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
        }

        if (!$dw->table('dim_customer')->where('customer_key', 0)->exists()) {
            $dw->table('dim_customer')->insert([
                'customer_key' => 0,
                'id_akun'      => '-',
                'nama'         => 'GUEST / Non-member',
            ]);
        }

        if (!$dw->table('dim_staf')->where('staf_key', 0)->exists()) {
            $dw->table('dim_staf')->insert([
                'staf_key' => 0,
                'id_akun'  => '-',
                'nama'     => 'Tidak ada (online)',
                'peran'    => '-',
            ]);
        }

        if (!$dw->table('dim_promo')->where('promo_key', 0)->exists()) {
            $dw->table('dim_promo')->insert([
                'promo_key'       => 0,
                'id_promo'        => '-',
                'nama_promo'      => 'Tanpa promo',
                'persen_diskon'   => 0,
                'kelompok_diskon' => 'Tanpa promo',
            ]);
        }

        $dw->table('dim_transaksi')->upsert([
            ['transaksi_key' => 1, 'kanal_penjualan' => 'ONLINE',  'metode_pembayaran' => 'QR'],
            ['transaksi_key' => 2, 'kanal_penjualan' => 'ONLINE',  'metode_pembayaran' => 'TUNAI'],
            ['transaksi_key' => 3, 'kanal_penjualan' => 'OFFLINE', 'metode_pembayaran' => 'QR'],
            ['transaksi_key' => 4, 'kanal_penjualan' => 'OFFLINE', 'metode_pembayaran' => 'TUNAI'],
        ], ['transaksi_key'], ['kanal_penjualan', 'metode_pembayaran']);

        $dw->table('dim_jenis_pengeluaran')->upsert([
            ['jenis_pengeluaran_key' => 1, 'jenis_pengeluaran' => 'PEMBELIAN_PRODUK', 'kelompok' => 'PEMBELIAN STOK'],
            ['jenis_pengeluaran_key' => 2, 'jenis_pengeluaran' => 'LISTRIK',          'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 3, 'jenis_pengeluaran' => 'INTERNET',         'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 4, 'jenis_pengeluaran' => 'SEWA',             'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 5, 'jenis_pengeluaran' => 'ONGKIR',           'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 6, 'jenis_pengeluaran' => 'PERAWATAN',        'kelompok' => 'OPERASIONAL'],
            ['jenis_pengeluaran_key' => 7, 'jenis_pengeluaran' => 'LAINNYA',          'kelompok' => 'OPERASIONAL'],
        ], ['jenis_pengeluaran_key'], ['jenis_pengeluaran', 'kelompok']);

        // ---------- 1. dim_waktu (Generate 2025-01-01 s.d. akhir tahun depan) ----------
        $this->info('1/6 Mengisi dim_waktu...');
        $minDate = collect([
            $src->table('penjualan')->min('tanggal_penjualan'),
            $src->table('pembelian_produk')->min('tanggal_pembelian'),
            $src->table('pengeluaran')->min('tanggal_pengeluaran'),
            '2025-01-01',
        ])->filter()->map(fn ($d) => Carbon::parse($d)->startOfYear())->min();

        $maxDate = collect([
            $src->table('penjualan')->max('tanggal_penjualan'),
            $src->table('pembelian_produk')->max('tanggal_pembelian'),
            $src->table('pengeluaran')->max('tanggal_pengeluaran'),
            now()->addYear()->endOfYear(),
            '2027-12-31',
        ])->filter()->map(fn ($d) => Carbon::parse($d)->endOfYear())->max();

        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $existingWaktuKeys = $dw->table('dim_waktu')->pluck('waktu_key')->flip()->all();
        $waktuRows = [];
        $cursor = $minDate->copy()->startOfDay();
        $endDay = $maxDate->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $key = (int) $cursor->format('Ymd');
            if (!isset($existingWaktuKeys[$key])) {
                $dow = $cursor->dayOfWeekIso;
                $month = $cursor->month;
                $waktuRows[] = [
                    'waktu_key'          => $key,
                    'tanggal'            => $cursor->toDateString(),
                    'hari'               => $cursor->day,
                    'nama_hari'          => $dayNames[$dow],
                    'hari_dalam_minggu'  => $dow,
                    'is_akhir_pekan'     => $dow >= 6 ? 1 : 0,
                    'minggu_dalam_tahun' => $cursor->isoWeek(),
                    'bulan'              => $month,
                    'nama_bulan'         => $monthNames[$month],
                    'kuartal'            => $cursor->quarter,
                    'semester'           => $month <= 6 ? 1 : 2,
                    'tahun'              => $cursor->year,
                ];
            }
            $cursor->addDay();
        }

        if ($waktuRows) {
            foreach (array_chunk($waktuRows, 100) as $chunk) {
                $dw->table('dim_waktu')->insert($chunk);
            }
        }

        // ---------- 2. dim_produk (gabungan varian_produk + produk + kategori_produk) ----------
        $this->info('2/6 Mengisi dim_produk...');
        $produkRows = $src->table('varian_produk as v')
            ->join('produk as p', 'v.id_produk', '=', 'p.id_produk')
            ->join('kategori_produk as k', 'p.id_kategori', '=', 'k.id_kategori')
            ->select(
                'v.id_varian',
                'v.ukuran',
                'v.warna',
                'v.stok_minimum',
                'p.id_produk',
                'p.nama_produk',
                'p.harga_jual',
                'p.status_produk',
                'k.id_kategori',
                'k.nama_kategori'
            )
            ->get()
            ->map(fn ($r) => [
                'id_varian'     => $r->id_varian,
                'ukuran'        => strtoupper(trim($r->ukuran)),
                'warna'         => strtolower(trim($r->warna)),
                'stok_minimum'  => (int) $r->stok_minimum,
                'id_produk'     => $r->id_produk,
                'nama_produk'   => trim($r->nama_produk),
                'harga_jual'    => $r->harga_jual,
                'status_produk' => $r->status_produk,
                'id_kategori'   => $r->id_kategori,
                'nama_kategori' => trim($r->nama_kategori),
            ])
            ->all();

        $this->upsertChunked(
            $dw,
            'dim_produk',
            $produkRows,
            ['id_varian'],
            ['ukuran', 'warna', 'stok_minimum', 'id_produk', 'nama_produk', 'harga_jual', 'status_produk', 'id_kategori', 'nama_kategori']
        );

        // ---------- 3. dim_customer (CUSTOMER) ----------
        $this->info('3/6 Mengisi dim_customer...');
        $customerRows = $src->table('akun')
            ->where('tipe_akun', 'CUSTOMER')
            ->get()
            ->map(function ($a) {
                $tgl = $a->created_at ? Carbon::parse($a->created_at) : null;
                return [
                    'id_akun'        => $a->id_akun,
                    'nama'           => $a->nama,
                    'username'       => $a->username,
                    'status_akun'    => $a->status_akun,
                    'tanggal_daftar' => $tgl?->toDateString(),
                    'tahun_daftar'   => $tgl?->year,
                ];
            })
            ->all();

        $this->upsertChunked(
            $dw,
            'dim_customer',
            $customerRows,
            ['id_akun'],
            ['nama', 'username', 'status_akun', 'tanggal_daftar', 'tahun_daftar']
        );

        // ---------- 4. dim_staf (KASIR & ADMIN) ----------
        $this->info('4/6 Mengisi dim_staf...');
        $stafRows = $src->table('akun')
            ->whereIn('tipe_akun', ['KASIR', 'ADMIN'])
            ->get()
            ->map(fn ($a) => [
                'id_akun'     => $a->id_akun,
                'nama'        => $a->nama,
                'peran'       => $a->tipe_akun,
                'status_akun' => $a->status_akun,
            ])
            ->all();

        $this->upsertChunked(
            $dw,
            'dim_staf',
            $stafRows,
            ['id_akun'],
            ['nama', 'peran', 'status_akun']
        );

        // ---------- 5. dim_promo ----------
        $this->info('5/6 Mengisi dim_promo...');
        $promoRows = $src->table('promo')
            ->get()
            ->map(function ($pr) {
                $mulai = Carbon::parse($pr->tanggal_mulai);
                $selesai = Carbon::parse($pr->tanggal_selesai);
                $pct = (float) $pr->persen_diskon;

                $kelompok = match (true) {
                    $pct <= 10 => '<=10%',
                    $pct <= 25 => '11-25%',
                    default    => '>25%',
                };

                return [
                    'id_promo'        => $pr->id_promo,
                    'nama_promo'      => $pr->nama_promo,
                    'persen_diskon'   => $pct,
                    'tanggal_mulai'   => $mulai->toDateTimeString(),
                    'tanggal_selesai' => $selesai->toDateTimeString(),
                    'durasi_hari'     => $mulai->diffInDays($selesai) + 1,
                    'kelompok_diskon' => $kelompok,
                ];
            })
            ->all();

        $this->upsertChunked(
            $dw,
            'dim_promo',
            $promoRows,
            ['id_promo'],
            ['nama_promo', 'persen_diskon', 'tanggal_mulai', 'tanggal_selesai', 'durasi_hari', 'kelompok_diskon']
        );

        // ---------- 6. dim_supplier ----------
        $this->info('6/6 Mengisi dim_supplier...');
        $supplierRows = $src->table('supplier')
            ->get()
            ->map(fn ($s) => [
                'id_supplier'   => $s->id_supplier,
                'nama_supplier' => $s->nama_supplier,
                'alamat'        => $s->alamat,
            ])
            ->all();

        $this->upsertChunked(
            $dw,
            'dim_supplier',
            $supplierRows,
            ['id_supplier'],
            ['nama_supplier', 'alamat']
        );

        $this->info('Seluruh dimensi Data Warehouse berhasil disinkronkan.');

        return self::SUCCESS;
    }
}

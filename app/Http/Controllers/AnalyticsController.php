<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'tahun'    => $request->filled('tahun') ? (int) $request->query('tahun') : null,
            'kuartal'  => $request->filled('kuartal') ? (int) $request->query('kuartal') : null,
            'kategori' => (string) $request->query('kategori', ''),
            'kanal'    => (string) $request->query('kanal', ''),
        ];

        $dwAvailable = false;
        $dwError = null;
        $options = [
            'tahunList'    => collect(),
            'kategoriList' => collect(),
        ];

        $kpi = [
            'omzet'              => 0.0,
            'laba_kotor'         => 0.0,
            'hpp'                => 0.0,
            'diskon_promo'       => 0.0,
            'diskon_poin'        => 0.0,
            'jumlah_transaksi'   => 0,
            'jumlah_unit'        => 0,
            'rata_rata_transaksi'=> 0.0,
            'total_pembelian'    => 0.0,
            'total_pengeluaran'  => 0.0,
            'arus_kas'           => 0.0,
        ];

        $monthlyTrend     = collect();
        $categorySales    = collect();
        $topProducts      = collect();
        $sizeColorMatrix  = collect();
        $channelPayment   = collect();
        $promoPerf        = collect();
        $memberVsGuest    = collect();
        $topCustomers     = collect();
        $cashierPerf      = collect();
        $busiestDays      = collect();
        $supplierPerf     = collect();
        $expenseByType    = collect();
        $monthlyCashFlow  = collect();
        $lowStockSnapshot = collect();

        try {
            $dwSchema = Schema::connection('dw');
            if (
                $dwSchema->hasTable('dim_waktu')
                && $dwSchema->hasTable('dim_produk')
                && $dwSchema->hasTable('fact_penjualan')
                && $dwSchema->hasTable('fact_pembelian')
                && $dwSchema->hasTable('fact_pengeluaran')
            ) {
                $dwAvailable = true;
                $dw = DB::connection('dw');

                $options['tahunList'] = $dw->table('fact_penjualan as f')
                    ->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')
                    ->select('w.tahun')
                    ->distinct()
                    ->orderByDesc('w.tahun')
                    ->pluck('w.tahun');

                if ($options['tahunList']->isEmpty()) {
                    $options['tahunList'] = $dw->table('dim_waktu')
                        ->select('tahun')
                        ->distinct()
                        ->orderByDesc('tahun')
                        ->pluck('tahun');
                }

                $options['kategoriList'] = $dw->table('dim_produk')
                    ->select('nama_kategori')
                    ->distinct()
                    ->orderBy('nama_kategori')
                    ->pluck('nama_kategori');

                $applyTime = function ($query) use ($filters) {
                    if ($filters['tahun']) {
                        $query->where('w.tahun', $filters['tahun']);
                    }
                    if ($filters['kuartal']) {
                        $query->where('w.kuartal', $filters['kuartal']);
                    }
                };

                $baseSales = function () use ($dw, $filters, $applyTime) {
                    $q = $dw->table('fact_penjualan as f')
                        ->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')
                        ->join('dim_produk as p', 'p.produk_key', '=', 'f.produk_key')
                        ->join('dim_transaksi as t', 't.transaksi_key', '=', 'f.transaksi_key');

                    $applyTime($q);

                    if ($filters['kategori'] !== '') {
                        $q->where('p.nama_kategori', $filters['kategori']);
                    }
                    if (in_array($filters['kanal'], ['ONLINE', 'OFFLINE'], true)) {
                        $q->where('t.kanal_penjualan', $filters['kanal']);
                    }

                    return $q;
                };

                $basePurchases = function () use ($dw, $filters, $applyTime) {
                    $q = $dw->table('fact_pembelian as fp')
                        ->join('dim_waktu as w', 'w.waktu_key', '=', 'fp.waktu_key')
                        ->join('dim_produk as p', 'p.produk_key', '=', 'fp.produk_key')
                        ->join('dim_supplier as s', 's.supplier_key', '=', 'fp.supplier_key');

                    $applyTime($q);

                    if ($filters['kategori'] !== '') {
                        $q->where('p.nama_kategori', $filters['kategori']);
                    }

                    return $q;
                };

                $baseExpenses = function () use ($dw, $applyTime) {
                    $q = $dw->table('fact_pengeluaran as fe')
                        ->join('dim_waktu as w', 'w.waktu_key', '=', 'fe.waktu_key')
                        ->join('dim_jenis_pengeluaran as jp', 'jp.jenis_pengeluaran_key', '=', 'fe.jenis_pengeluaran_key');

                    $applyTime($q);

                    return $q;
                };

                // Q1 KPI Aggregations
                $salesSummary = $baseSales()
                    ->selectRaw('
                        COALESCE(SUM(f.pendapatan_bersih), 0) as omzet,
                        COALESCE(SUM(f.laba_kotor), 0) as laba_kotor,
                        COALESCE(SUM(f.hpp), 0) as hpp,
                        COALESCE(SUM(f.diskon_promo), 0) as diskon_promo,
                        COALESCE(SUM(f.diskon_poin), 0) as diskon_poin,
                        COUNT(DISTINCT f.id_penjualan) as jumlah_transaksi,
                        COALESCE(SUM(f.jumlah), 0) as jumlah_unit
                    ')
                    ->first();

                $totalPembelian = (float) ($basePurchases()->sum('fp.total_biaya') ?? 0);
                $totalPengeluaran = (float) ($baseExpenses()->sum('fe.nominal') ?? 0);

                $omzet = (float) ($salesSummary->omzet ?? 0);
                $trxCount = (int) ($salesSummary->jumlah_transaksi ?? 0);

                $kpi = [
                    'omzet'               => $omzet,
                    'laba_kotor'          => (float) ($salesSummary->laba_kotor ?? 0),
                    'hpp'                 => (float) ($salesSummary->hpp ?? 0),
                    'diskon_promo'        => (float) ($salesSummary->diskon_promo ?? 0),
                    'diskon_poin'         => (float) ($salesSummary->diskon_poin ?? 0),
                    'jumlah_transaksi'    => $trxCount,
                    'jumlah_unit'         => (int) ($salesSummary->jumlah_unit ?? 0),
                    'rata_rata_transaksi' => $trxCount > 0 ? round($omzet / $trxCount, 2) : 0.0,
                    'total_pembelian'     => $totalPembelian,
                    'total_pengeluaran'   => $totalPengeluaran,
                    'arus_kas'            => $omzet - $totalPengeluaran,
                ];

                // Q1: Roll-up Bulanan (Omzet & Laba Kotor)
                $monthlyTrend = $baseSales()
                    ->select(
                        'w.tahun',
                        'w.bulan',
                        'w.nama_bulan',
                        DB::raw('COUNT(DISTINCT f.id_penjualan) as transaksi'),
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet'),
                        DB::raw('SUM(f.laba_kotor) as laba')
                    )
                    ->groupBy('w.tahun', 'w.bulan', 'w.nama_bulan')
                    ->orderBy('w.tahun')
                    ->orderBy('w.bulan')
                    ->get();

                // Q2: Omzet & Qty per Kategori + Top 10 Produk
                $categorySales = $baseSales()
                    ->select(
                        'p.nama_kategori',
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet'),
                        DB::raw('SUM(f.laba_kotor) as laba')
                    )
                    ->groupBy('p.nama_kategori')
                    ->orderByDesc('omzet')
                    ->get();

                $topProducts = $baseSales()
                    ->select(
                        'p.id_produk',
                        'p.nama_produk',
                        'p.nama_kategori',
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet'),
                        DB::raw('SUM(f.laba_kotor) as laba')
                    )
                    ->groupBy('p.id_produk', 'p.nama_produk', 'p.nama_kategori')
                    ->orderByDesc('qty')
                    ->limit(10)
                    ->get();

                // Q3: Pivot Ukuran x Warna Terlaris
                $sizeColorMatrix = $baseSales()
                    ->select(
                        'p.ukuran',
                        'p.warna',
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet')
                    )
                    ->groupBy('p.ukuran', 'p.warna')
                    ->orderByDesc('qty')
                    ->limit(12)
                    ->get();

                // Q4: Kanal Penjualan & Metode Pembayaran
                $channelPayment = $baseSales()
                    ->select(
                        't.kanal_penjualan',
                        't.metode_pembayaran',
                        DB::raw('COUNT(DISTINCT f.id_penjualan) as transaksi'),
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet')
                    )
                    ->groupBy('t.kanal_penjualan', 't.metode_pembayaran')
                    ->orderByDesc('omzet')
                    ->get();

                // Q5: Efektivitas Promo
                $promoPerf = $baseSales()
                    ->join('dim_promo as pr', 'pr.promo_key', '=', 'f.promo_key')
                    ->select(
                        'pr.promo_key',
                        'pr.nama_promo',
                        'pr.kelompok_diskon',
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet'),
                        DB::raw('SUM(f.diskon_promo) as total_diskon')
                    )
                    ->groupBy('pr.promo_key', 'pr.nama_promo', 'pr.kelompok_diskon')
                    ->orderByDesc('omzet')
                    ->get();

                // Q6: Member vs Guest & Top Customer
                $memberVsGuest = $baseSales()
                    ->selectRaw("
                        CASE WHEN f.customer_key = 0 THEN 'GUEST (Non-member)' ELSE 'MEMBER' END as tipe_pembeli,
                        COUNT(DISTINCT f.id_penjualan) as transaksi,
                        SUM(f.jumlah) as qty,
                        SUM(f.pendapatan_bersih) as omzet
                    ")
                    ->groupBy(DB::raw("CASE WHEN f.customer_key = 0 THEN 'GUEST (Non-member)' ELSE 'MEMBER' END"))
                    ->orderByDesc('omzet')
                    ->get();

                $topCustomers = $baseSales()
                    ->join('dim_customer as c', 'c.customer_key', '=', 'f.customer_key')
                    ->where('f.customer_key', '>', 0)
                    ->select(
                        'c.id_akun',
                        'c.nama',
                        DB::raw('COUNT(DISTINCT f.id_penjualan) as transaksi'),
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet')
                    )
                    ->groupBy('c.id_akun', 'c.nama')
                    ->orderByDesc('omzet')
                    ->limit(8)
                    ->get();

                // Q7: Kinerja Kasir
                $cashierPerf = $baseSales()
                    ->join('dim_staf as st', 'st.staf_key', '=', 'f.staf_key')
                    ->where('f.staf_key', '>', 0)
                    ->select(
                        'st.id_akun',
                        'st.nama',
                        DB::raw('COUNT(DISTINCT f.id_penjualan) as transaksi'),
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet')
                    )
                    ->groupBy('st.id_akun', 'st.nama')
                    ->orderByDesc('omzet')
                    ->get();

                // Q8: Hari Teramai (Senin - Minggu)
                $busiestDays = $baseSales()
                    ->select(
                        'w.hari_dalam_minggu',
                        'w.nama_hari',
                        DB::raw('COUNT(DISTINCT f.id_penjualan) as transaksi'),
                        DB::raw('SUM(f.jumlah) as qty'),
                        DB::raw('SUM(f.pendapatan_bersih) as omzet')
                    )
                    ->groupBy('w.hari_dalam_minggu', 'w.nama_hari')
                    ->orderBy('w.hari_dalam_minggu')
                    ->get();

                // Q9: Pembelian per Supplier
                $supplierPerf = $basePurchases()
                    ->select(
                        's.id_supplier',
                        's.nama_supplier',
                        DB::raw('COUNT(DISTINCT fp.id_pembelian) as jumlah_po'),
                        DB::raw('SUM(fp.jumlah) as qty'),
                        DB::raw('SUM(fp.total_biaya) as total_biaya')
                    )
                    ->groupBy('s.id_supplier', 's.nama_supplier')
                    ->orderByDesc('total_biaya')
                    ->get();

                // Q10: Pengeluaran per Jenis
                $expenseByType = $baseExpenses()
                    ->select(
                        'jp.kelompok',
                        'jp.jenis_pengeluaran',
                        DB::raw('COUNT(*) as jumlah_catat'),
                        DB::raw('SUM(fe.nominal) as total_nominal')
                    )
                    ->groupBy('jp.kelompok', 'jp.jenis_pengeluaran')
                    ->orderByDesc('total_nominal')
                    ->get();

                // Q11: Drill-across Arus Kas Bulanan (Pemasukan fact_penjualan vs Pengeluaran fact_pengeluaran)
                $monthlyExp = $baseExpenses()
                    ->select(
                        'w.tahun',
                        'w.bulan',
                        'w.nama_bulan',
                        DB::raw('SUM(fe.nominal) as pengeluaran')
                    )
                    ->groupBy('w.tahun', 'w.bulan', 'w.nama_bulan')
                    ->get()
                    ->keyBy(fn ($r) => "{$r->tahun}-{$r->bulan}");

                $monthlyRev = $monthlyTrend->keyBy(fn ($r) => "{$r->tahun}-{$r->bulan}");
                $allPeriodKeys = $monthlyRev->keys()->merge($monthlyExp->keys())->unique()->sort(function ($a, $b) {
                    [$ya, $ma] = array_map('intval', explode('-', $a));
                    [$yb, $mb] = array_map('intval', explode('-', $b));
                    return ($ya <=> $yb) ?: ($ma <=> $mb);
                });

                $monthlyCashFlow = $allPeriodKeys->map(function ($key) use ($monthlyRev, $monthlyExp) {
                    $rev = $monthlyRev->get($key);
                    $exp = $monthlyExp->get($key);
                    $pemasukan = (float) ($rev->omzet ?? 0);
                    $pengeluaran = (float) ($exp->pengeluaran ?? 0);

                    return (object) [
                        'tahun'       => $rev->tahun ?? $exp->tahun,
                        'bulan'       => $rev->bulan ?? $exp->bulan,
                        'nama_bulan'  => $rev->nama_bulan ?? $exp->nama_bulan,
                        'pemasukan'   => $pemasukan,
                        'pengeluaran' => $pengeluaran,
                        'arus_kas'    => $pemasukan - $pengeluaran,
                    ];
                })->values();

                // Q12: Snapshot Stok Rendah (Periodic Snapshot Fact)
                if ($dwSchema->hasTable('fact_stok_snapshot')) {
                    $latestSnapWaktu = $dw->table('fact_stok_snapshot')->max('waktu_key');
                    if ($latestSnapWaktu) {
                        $lowStockSnapshot = $dw->table('fact_stok_snapshot as fs')
                            ->join('dim_produk as p', 'p.produk_key', '=', 'fs.produk_key')
                            ->where('fs.waktu_key', $latestSnapWaktu)
                            ->where('fs.is_low_stock', 1)
                            ->select('p.id_varian', 'p.nama_produk', 'p.nama_kategori', 'p.ukuran', 'p.warna', 'fs.stok', 'fs.stok_minimum')
                            ->orderBy('fs.stok')
                            ->limit(10)
                            ->get();
                    }
                }
            }
        } catch (Throwable $e) {
            $dwAvailable = false;
            $dwError = $e->getMessage();
        }

        return view(
            'admin.analitik.index',
            compact(
                'dwAvailable',
                'dwError',
                'filters',
                'options',
                'kpi',
                'monthlyTrend',
                'categorySales',
                'topProducts',
                'sizeColorMatrix',
                'channelPayment',
                'promoPerf',
                'memberVsGuest',
                'topCustomers',
                'cashierPerf',
                'busiestDays',
                'supplierPerf',
                'expenseByType',
                'monthlyCashFlow',
                'lowStockSnapshot'
            )
        );
    }

    public function sync()
    {
        try {
            $dwSchema = Schema::connection('dw');
            if (
                !$dwSchema->hasTable('dim_waktu')
                || !$dwSchema->hasTable('dim_promo')
                || !$dwSchema->hasTable('fact_penjualan')
                || !$dwSchema->hasTable('fact_stok_snapshot')
            ) {
                Artisan::call('migrate', [
                    '--database' => 'dw',
                    '--path'     => 'database/migrations/dw',
                    '--force'    => true,
                ]);
            }

            Artisan::call('dw:sync');

            return redirect()
                ->route('admin.analitik')
                ->with(
                    'success',
                    'Data Warehouse berhasil disinkronkan (dimensi & seluruh tabel fakta diperbarui).'
                );
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.analitik')
                ->withErrors([
                    'dw' => 'Gagal menjalankan ETL Data Warehouse: '.$e->getMessage(),
                ]);
        }
    }
}

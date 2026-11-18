<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $income = (float) DB::table('penjualan')
            ->where('status_pembayaran', 'BERHASIL')
            ->sum('nominal_bayar');

        $purchase = (float) DB::table('pengeluaran')
            ->where('jenis_pengeluaran', 'PEMBELIAN_PRODUK')
            ->sum('nominal');

        $operational = (float) DB::table('pengeluaran')
            ->where('jenis_pengeluaran', '!=', 'PEMBELIAN_PRODUK')
            ->sum('nominal');

        $totalExpense = (float) DB::table('pengeluaran')
            ->sum('nominal');

        $cashFlow = $income - $totalExpense;

        $best = DB::table('detail_penjualan as d')
            ->join(
                'penjualan as s',
                'd.id_penjualan',
                '=',
                's.id_penjualan'
            )
            ->join(
                'varian_produk as v',
                'd.id_varian',
                '=',
                'v.id_varian'
            )
            ->join(
                'produk as p',
                'v.id_produk',
                '=',
                'p.id_produk'
            )
            ->where('s.status_pembayaran', 'BERHASIL')
            ->select(
                'p.id_produk',
                'p.nama_produk',
                DB::raw('SUM(d.jumlah) as qty')
            )
            ->groupBy('p.id_produk', 'p.nama_produk')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $low = DB::table('varian_produk as v')
            ->join('produk as p', 'v.id_produk', '=', 'p.id_produk')
            ->where('p.status_produk', 'AKTIF')
            ->whereColumn('v.stok', '<=', 'v.stok_minimum')
            ->select('v.*', 'p.nama_produk')
            ->orderBy('v.stok')
            ->get();

        // ---------------- Data Warehouse (OLAP Analytics) ----------------
        $dwFilters = [
            'periode'  => (string) $request->query('periode', 'semua'),
            'tahun'    => $request->filled('tahun') ? (int) $request->query('tahun') : null,
            'kuartal'  => $request->filled('kuartal') ? (int) $request->query('kuartal') : null,
            'bulan'    => $request->filled('bulan') ? (int) $request->query('bulan') : null,
            'kanal'    => (string) $request->query('kanal', ''),
            'kategori' => (string) $request->query('kategori', ''),
        ];

        $dwAvailable = false;
        $dwError = null;
        $dwOptions = [
            'tahunList'    => collect(),
            'kategoriList' => collect(),
        ];
        $dwSummary = [
            'total_transaksi_penjualan' => 0,
            'total_baris_penjualan'     => 0,
            'total_unit_terjual'        => 0,
            'total_omzet_bersih'        => 0.0,
            'total_diskon_poin'         => 0.0,
            'total_po_pembelian'        => 0,
            'total_unit_dibeli'         => 0,
            'total_biaya_pembelian'     => 0.0,
            'total_pengeluaran_ops'     => 0.0,
            'total_seluruh_pengeluaran' => 0.0,
            'laba_bersih_dw'            => 0.0,
        ];
        $dwSalesTrend        = collect();
        $dwPurchaseTrend     = collect();
        $dwCategorySales     = collect();
        $dwProductSales      = collect();
        $dwChannelRatio      = collect();
        $dwPaymentRatio      = collect();
        $dwSupplierPurchases = collect();
        $dwTopCustomers      = collect();
        $dwStaffPerformance  = collect();
        $dwExpenseBreakdown  = collect();

        try {
            $dwSchema = Schema::connection('dw');
            if (
                $dwSchema->hasTable('dim_waktu')
                && $dwSchema->hasTable('dim_produk')
                && $dwSchema->hasTable('fakta_penjualan')
                && $dwSchema->hasTable('fakta_pembelian_produk')
            ) {
                $dwAvailable = true;
                $dw = DB::connection('dw');

                $dwOptions['tahunList'] = $dw->table('dim_waktu')
                    ->select('tahun')
                    ->distinct()
                    ->orderByDesc('tahun')
                    ->pluck('tahun');

                $dwOptions['kategoriList'] = $dw->table('dim_produk')
                    ->select('kategori')
                    ->distinct()
                    ->orderBy('kategori')
                    ->pluck('kategori');

                $applyTimeFilter = function ($query, string $alias = 'w') use ($dwFilters) {
                    $now = now();
                    if ($dwFilters['periode'] === 'tahun_ini') {
                        $query->where("{$alias}.tahun", $now->year);
                    } elseif ($dwFilters['periode'] === 'bulan_ini') {
                        $query->where("{$alias}.tahun", $now->year)
                            ->where("{$alias}.bulan", $now->month);
                    }

                    if ($dwFilters['tahun']) {
                        $query->where("{$alias}.tahun", $dwFilters['tahun']);
                    }
                    if ($dwFilters['kuartal']) {
                        $query->where("{$alias}.kuartal", $dwFilters['kuartal']);
                    }
                    if ($dwFilters['bulan']) {
                        $query->where("{$alias}.bulan", $dwFilters['bulan']);
                    }
                };

                $baseSales = function () use ($dw, $applyTimeFilter, $dwFilters) {
                    $q = $dw->table('fakta_penjualan as fp')
                        ->join('dim_waktu as w', 'fp.id_waktu', '=', 'w.id_waktu')
                        ->join('dim_produk as dp', 'fp.sk_produk', '=', 'dp.sk_produk');

                    $applyTimeFilter($q, 'w');

                    if (in_array($dwFilters['kanal'], ['ONLINE', 'OFFLINE'], true)) {
                        $q->where('fp.kanal_penjualan', $dwFilters['kanal']);
                    }
                    if ($dwFilters['kategori'] !== '') {
                        $q->where('dp.kategori', $dwFilters['kategori']);
                    }

                    return $q;
                };

                $basePurchases = function () use ($dw, $applyTimeFilter, $dwFilters) {
                    $q = $dw->table('fakta_pembelian_produk as fpb')
                        ->join('dim_waktu as w', 'fpb.id_waktu', '=', 'w.id_waktu')
                        ->join('dim_produk as dp', 'fpb.sk_produk', '=', 'dp.sk_produk')
                        ->join('dim_supplier as ds', 'fpb.sk_supplier', '=', 'ds.sk_supplier');

                    $applyTimeFilter($q, 'w');

                    if ($dwFilters['kategori'] !== '') {
                        $q->where('dp.kategori', $dwFilters['kategori']);
                    }

                    return $q;
                };

                $salesAgg = $baseSales()
                    ->selectRaw('
                        COUNT(DISTINCT fp.no_penjualan) as total_transaksi,
                        COUNT(*) as total_baris,
                        COALESCE(SUM(fp.jumlah_terjual), 0) as total_unit,
                        COALESCE(SUM(fp.total_bersih_item), 0) as total_omzet,
                        COALESCE(SUM(fp.diskon_poin_alokasi), 0) as total_diskon_poin
                    ')
                    ->first();

                $purchaseAgg = $basePurchases()
                    ->selectRaw('
                        COUNT(DISTINCT fpb.no_pembelian) as total_po,
                        COALESCE(SUM(fpb.jumlah_dibeli), 0) as total_unit,
                        COALESCE(SUM(fpb.total_biaya_pembelian), 0) as total_biaya
                    ')
                    ->first();

                $dwSummary = [
                    'total_transaksi_penjualan' => (int) ($salesAgg->total_transaksi ?? 0),
                    'total_baris_penjualan'     => (int) ($salesAgg->total_baris ?? 0),
                    'total_unit_terjual'        => (int) ($salesAgg->total_unit ?? 0),
                    'total_omzet_bersih'        => (float) ($salesAgg->total_omzet ?? 0),
                    'total_diskon_poin'         => (float) ($salesAgg->total_diskon_poin ?? 0),
                    'total_po_pembelian'        => (int) ($purchaseAgg->total_po ?? 0),
                    'total_unit_dibeli'         => (int) ($purchaseAgg->total_unit ?? 0),
                    'total_biaya_pembelian'     => (float) ($purchaseAgg->total_biaya ?? 0),
                    'total_pengeluaran_ops'     => 0.0,
                    'total_seluruh_pengeluaran' => (float) ($purchaseAgg->total_biaya ?? 0),
                    'laba_bersih_dw'            => (float) ($salesAgg->total_omzet ?? 0) - (float) ($purchaseAgg->total_biaya ?? 0),
                ];

                if ($dwSchema->hasTable('fakta_pengeluaran')) {
                    $baseExpenses = function () use ($dw, $applyTimeFilter) {
                        $q = $dw->table('fakta_pengeluaran as fpe')
                            ->join('dim_waktu as w', 'fpe.id_waktu', '=', 'w.id_waktu')
                            ->join('dim_staf as dst', 'fpe.sk_staf', '=', 'dst.sk_staf');

                        $applyTimeFilter($q, 'w');

                        return $q;
                    };

                    $opsExpense = (float) $baseExpenses()
                        ->where('fpe.kategori_biaya', 'OPERASIONAL')
                        ->sum('fpe.nominal_pengeluaran');

                    $allExpense = (float) $baseExpenses()
                        ->sum('fpe.nominal_pengeluaran');

                    $dwSummary['total_pengeluaran_ops']     = $opsExpense;
                    $dwSummary['total_seluruh_pengeluaran'] = $allExpense;
                    $dwSummary['laba_bersih_dw']            = $dwSummary['total_omzet_bersih'] - $allExpense;

                    $dwExpenseBreakdown = $baseExpenses()
                        ->select(
                            'fpe.kategori_biaya',
                            'fpe.jenis_pengeluaran',
                            DB::raw('COUNT(*) as jumlah_catat'),
                            DB::raw('SUM(fpe.nominal_pengeluaran) as total_nominal')
                        )
                        ->groupBy('fpe.kategori_biaya', 'fpe.jenis_pengeluaran')
                        ->orderByDesc('total_nominal')
                        ->get();
                }

                $dwSalesTrend = $baseSales()
                    ->select(
                        'w.tahun',
                        'w.bulan',
                        'w.nama_bulan',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('w.tahun', 'w.bulan', 'w.nama_bulan')
                    ->orderBy('w.tahun')
                    ->orderBy('w.bulan')
                    ->get();

                $dwPurchaseTrend = $basePurchases()
                    ->select(
                        'w.tahun',
                        'w.bulan',
                        'w.nama_bulan',
                        DB::raw('COUNT(DISTINCT fpb.no_pembelian) as po'),
                        DB::raw('SUM(fpb.jumlah_dibeli) as qty'),
                        DB::raw('SUM(fpb.total_biaya_pembelian) as biaya')
                    )
                    ->groupBy('w.tahun', 'w.bulan', 'w.nama_bulan')
                    ->orderBy('w.tahun')
                    ->orderBy('w.bulan')
                    ->get();

                $dwCategorySales = $baseSales()
                    ->select(
                        'dp.kategori',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('dp.kategori')
                    ->orderByDesc('omzet')
                    ->get();

                $dwProductSales = $baseSales()
                    ->select(
                        'dp.id_produk',
                        'dp.nama_produk',
                        'dp.kategori',
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('dp.id_produk', 'dp.nama_produk', 'dp.kategori')
                    ->orderByDesc('qty')
                    ->limit(10)
                    ->get();

                $dwChannelRatio = $baseSales()
                    ->select(
                        'fp.kanal_penjualan',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('fp.kanal_penjualan')
                    ->orderByDesc('omzet')
                    ->get();

                $dwPaymentRatio = $baseSales()
                    ->select(
                        'fp.metode_pembayaran',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('fp.metode_pembayaran')
                    ->orderByDesc('omzet')
                    ->get();

                $dwSupplierPurchases = $basePurchases()
                    ->select(
                        'ds.id_supplier',
                        'ds.nama_supplier',
                        'ds.kota_supplier',
                        DB::raw('COUNT(DISTINCT fpb.no_pembelian) as po'),
                        DB::raw('SUM(fpb.jumlah_dibeli) as qty'),
                        DB::raw('SUM(fpb.total_biaya_pembelian) as biaya')
                    )
                    ->groupBy('ds.id_supplier', 'ds.nama_supplier', 'ds.kota_supplier')
                    ->orderByDesc('biaya')
                    ->get();

                $dwTopCustomers = $baseSales()
                    ->join('dim_pelanggan as dpel', 'fp.sk_pelanggan', '=', 'dpel.sk_pelanggan')
                    ->select(
                        'dpel.id_akun',
                        'dpel.nama_pelanggan',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('dpel.id_akun', 'dpel.nama_pelanggan')
                    ->orderByDesc('omzet')
                    ->limit(5)
                    ->get();

                $dwStaffPerformance = $baseSales()
                    ->join('dim_staf as dst', 'fp.sk_staf', '=', 'dst.sk_staf')
                    ->select(
                        'dst.id_akun',
                        'dst.nama_staf',
                        'dst.tipe_akun',
                        DB::raw('COUNT(DISTINCT fp.no_penjualan) as transaksi'),
                        DB::raw('SUM(fp.jumlah_terjual) as qty'),
                        DB::raw('SUM(fp.total_bersih_item) as omzet')
                    )
                    ->groupBy('dst.id_akun', 'dst.nama_staf', 'dst.tipe_akun')
                    ->orderByDesc('omzet')
                    ->get();
            }
        } catch (Throwable $e) {
            $dwAvailable = false;
            $dwError = $e->getMessage();
        }

        return view(
            'admin.reports.index',
            compact(
                'income',
                'purchase',
                'operational',
                'totalExpense',
                'cashFlow',
                'best',
                'low',
                'dwAvailable',
                'dwError',
                'dwFilters',
                'dwOptions',
                'dwSummary',
                'dwSalesTrend',
                'dwPurchaseTrend',
                'dwCategorySales',
                'dwProductSales',
                'dwChannelRatio',
                'dwPaymentRatio',
                'dwSupplierPurchases',
                'dwTopCustomers',
                'dwStaffPerformance',
                'dwExpenseBreakdown'
            )
        );
    }

    public function syncDw()
    {
        try {
            $dwSchema = Schema::connection('dw');
            if (!$dwSchema->hasTable('dim_waktu') || !$dwSchema->hasTable('fakta_pengeluaran')) {
                Artisan::call('migrate', [
                    '--database' => 'dw',
                    '--path'     => 'database/migrations/dw',
                    '--force'    => true,
                ]);
            }

            Artisan::call('dw:etl-pembelian');
            Artisan::call('dw:etl-penjualan');
            Artisan::call('dw:etl-pengeluaran');

            return redirect()
                ->route('admin.reports.index')
                ->with(
                    'success',
                    'Sinkronisasi ETL Data Warehouse (pembelian, penjualan & pengeluaran) berhasil dijalankan.'
                );
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.reports.index')
                ->withErrors([
                    'dw' => 'Gagal menjalankan ETL Data Warehouse: '.$e->getMessage(),
                ]);
        }
    }
}


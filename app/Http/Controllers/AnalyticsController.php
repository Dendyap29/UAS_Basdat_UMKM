<?php

namespace App\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dashboard analitik FORMA. Hanya membaca Data Warehouse (koneksi "dw").
 *
 * Aturan yang dijaga:
 * - Filter global hanya periode (tahun, kuartal) karena ketiga fakta punya dim_waktu.
 * - Perbandingan antar-fakta (drill-across) dihitung dengan mengagregasi tiap fakta sendiri
 *   pada dimensi bersama, lalu hasilnya digabung di PHP. Fakta tidak pernah di-JOIN langsung.
 * - Rasio (margin, rata-rata transaksi) dihitung dari total, bukan dirata-rata per baris.
 */
class AnalyticsController extends Controller
{
    private const NAMA_BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function index(Request $request)
    {
        $filters = [
            'tahun'   => $request->filled('tahun') ? (int) $request->query('tahun') : null,
            'kuartal' => $request->filled('kuartal') ? (int) $request->query('kuartal') : null,
            'level'   => $request->query('level') === 'kuartal' ? 'kuartal' : 'bulan',
            'kat'     => trim((string) $request->query('kat', '')),
            'produk'  => trim((string) $request->query('produk', '')),
            'urut'    => $request->query('urut') === 'terendah' ? 'terendah' : 'tertinggi',
        ];

        $data = ['dwAvailable' => false, 'dwError' => null, 'filters' => $filters];

        try {
            $required = ['dim_waktu', 'dim_produk', 'dim_pelanggan', 'dim_promo', 'dim_transaksi', 'dim_supplier',
                'fact_penjualan', 'fact_pembelian', 'fact_pengeluaran'];
            foreach ($required as $table) {
                if (! Schema::connection('dw')->hasTable($table)) {
                    throw new \RuntimeException("Tabel {$table} belum ada di umkm_dw.");
                }
            }

            $dw = DB::connection('dw');
            if ((int) $dw->table('fact_penjualan')->count() === 0) {
                throw new \RuntimeException('Tabel fakta masih kosong. Jalankan job Pentaho job_etl_umkm_dw.kjb.');
            }

            $data = array_merge($data, $this->build($dw, $filters), ['dwAvailable' => true]);
        } catch (Throwable $e) {
            $data['dwError'] = $e->getMessage();
        }

        return view('admin.analitik.index', $data);
    }

    private function build(ConnectionInterface $dw, array $f): array
    {
        $time = function (Builder $q) use ($f): Builder {
            if ($f['tahun']) {
                $q->where('w.tahun', $f['tahun']);
            }
            if ($f['kuartal']) {
                $q->where('w.kuartal', $f['kuartal']);
            }

            return $q;
        };
        $sales = fn () => $time($dw->table('fact_penjualan as f')->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key'));
        $buys = fn () => $time($dw->table('fact_pembelian as b')->join('dim_waktu as w', 'w.waktu_key', '=', 'b.waktu_key'));
        $costs = fn () => $time($dw->table('fact_pengeluaran as e')->join('dim_waktu as w', 'w.waktu_key', '=', 'e.waktu_key'));

        // ---------------- KPI ----------------
        $s = $sales()->selectRaw('COALESCE(SUM(f.pendapatan_bersih),0) omzet, COALESCE(SUM(f.hpp),0) hpp,
                COALESCE(SUM(f.laba_kotor),0) laba, COALESCE(SUM(f.jumlah),0) unit,
                COUNT(DISTINCT f.id_penjualan) trx, COALESCE(SUM(f.diskon_promo),0) diskon_promo')->first();
        $pembelian = (float) $buys()->sum('b.total_biaya');
        $operasional = (float) $costs()->sum('e.nominal');
        $omzet = (float) $s->omzet;
        $laba = (float) $s->laba;

        $kpi = [
            'omzet'            => $omzet,
            'laba_kotor'       => $laba,
            'margin'           => $omzet > 0 ? $laba / $omzet : null,
            'hpp'              => (float) $s->hpp,
            'operasional'      => $operasional,
            'laba_operasional' => $laba - $operasional,
            'pembelian'        => $pembelian,
            'arus_kas'         => $omzet - $pembelian - $operasional,
            'stok_bertambah'   => $pembelian - (float) $s->hpp, // = laba operasional - arus kas
            'transaksi'        => (int) $s->trx,
            'unit'             => (int) $s->unit,
            'aov'              => $s->trx > 0 ? $omzet / $s->trx : 0,
            'diskon_promo'     => (float) $s->diskon_promo,
        ];

        // ---------------- V1 tren (roll-up / drill-down waktu) ----------------
        $periodCols = $f['level'] === 'kuartal' ? ['w.tahun', 'w.kuartal'] : ['w.tahun', 'w.bulan'];
        $trend = $sales()->select($periodCols)
            ->selectRaw('SUM(f.pendapatan_bersih) omzet, SUM(f.laba_kotor) laba')
            ->groupBy($periodCols)->orderBy($periodCols[0])->orderBy($periodCols[1])->get()
            ->map(fn ($r) => (object) [
                'label' => $f['level'] === 'kuartal' ? "Q{$r->kuartal} {$r->tahun}" : self::NAMA_BULAN[$r->bulan].' '.$r->tahun,
                'omzet' => (float) $r->omzet, 'laba' => (float) $r->laba,
            ]);

        // ---------------- V2 drill-across uang masuk vs keluar per bulan ----------------
        $byMonth = fn (Builder $q, string $expr) => $q->select('w.tahun', 'w.bulan')->selectRaw("SUM({$expr}) v")
            ->groupBy('w.tahun', 'w.bulan')->get()->keyBy(fn ($r) => $r->tahun * 100 + $r->bulan);
        $mIn = $byMonth($sales(), 'f.pendapatan_bersih');
        $mBuy = $byMonth($buys(), 'b.total_biaya');
        $mCost = $byMonth($costs(), 'e.nominal');
        $cash = $mIn->keys()->merge($mBuy->keys())->merge($mCost->keys())->unique()->sort()->values()
            ->map(function ($k) use ($mIn, $mBuy, $mCost) {
                $in = (float) ($mIn[$k]->v ?? 0);
                $buy = (float) ($mBuy[$k]->v ?? 0);
                $cost = (float) ($mCost[$k]->v ?? 0);

                return (object) ['label' => self::NAMA_BULAN[$k % 100].' '.intdiv($k, 100), 'masuk' => $in,
                    'pembelian' => $buy, 'operasional' => $cost, 'arus_kas' => $in - $buy - $cost];
            });

        // ---------------- V3 kinerja produk (drill-down + drill-across) ----------------
        [$level, $keyCol, $labelCol] = match (true) {
            $f['produk'] !== '' => ['varian', 'p.id_varian', null],
            $f['kat'] !== '' => ['produk', 'p.id_produk', 'p.nama_produk'],
            default => ['kategori', 'p.nama_kategori', 'p.nama_kategori'],
        };
        $scope = function (Builder $q) use ($f) {
            if ($f['produk'] !== '') {
                $q->where('p.id_produk', $f['produk']);
            } elseif ($f['kat'] !== '') {
                $q->where('p.nama_kategori', $f['kat']);
            }

            return $q;
        };
        $labelSelect = $level === 'varian' ? ['p.ukuran', 'p.warna'] : [$labelCol];
        $salesByItem = $scope($sales()->join('dim_produk as p', 'p.produk_key', '=', 'f.produk_key'))
            ->selectRaw("{$keyCol} k")->addSelect($labelSelect)
            ->selectRaw('SUM(f.jumlah) unit, SUM(f.pendapatan_bersih) omzet, SUM(f.laba_kotor) laba, MAX(w.tanggal) terakhir')
            ->groupBy(array_merge([$keyCol], $labelSelect))->get()->keyBy('k');
        $buyByItem = $scope($buys()->join('dim_produk as p', 'p.produk_key', '=', 'b.produk_key'))
            ->selectRaw("{$keyCol} k, SUM(b.jumlah) unit_beli")->groupBy($keyCol)->get()->keyBy('k');
        $catalog = $scope($dw->table('dim_produk as p'))->selectRaw("{$keyCol} k")->addSelect($labelSelect)
            ->distinct()->get()->keyBy('k');
        $lastDate = $dw->table('fact_penjualan as f')->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')->max('w.tanggal');

        $products = $catalog->map(function ($c, $k) use ($salesByItem, $buyByItem, $level, $labelCol, $lastDate) {
            $s = $salesByItem[$k] ?? null;
            $unit = (int) ($s->unit ?? 0);
            $beli = (int) ($buyByItem[$k]->unit_beli ?? 0);
            $omzet = (float) ($s->omzet ?? 0);
            $terakhir = $s->terakhir ?? null;

            return (object) [
                'key'       => $k,
                'label'     => $level === 'varian' ? "{$c->ukuran} / {$c->warna}" : $c->{str_replace('p.', '', $labelCol)},
                'unit'      => $unit,
                'omzet'     => $omzet,
                'margin'    => $omzet > 0 ? ($s->laba / $omzet) : null,
                'unit_beli' => $beli,
                'selisih'   => $beli - $unit,
                'terakhir'  => $terakhir,
                'diam_hari' => $terakhir && $lastDate ? (int) round((strtotime($lastDate) - strtotime($terakhir)) / 86400) : null,
            ];
        })->sortBy([['unit', $f['urut'] === 'terendah' ? 'asc' : 'desc'], ['label', 'asc']])->values();
        $productLevel = $level;
        $productTitle = match ($level) {
            'varian' => 'Varian produk '.($dw->table('dim_produk')->where('id_produk', $f['produk'])->value('nama_produk') ?? $f['produk']),
            'produk' => 'Produk dalam kategori '.$f['kat'],
            default => 'Semua kategori',
        };

        // Varian diam: masih ada sisa (unit dibeli - unit terjual > 0) tetapi tidak terjual >= 90 hari
        // sampai tanggal data terakhir. Memakai seluruh periode karena yang dinilai adalah posisi saat ini.
        $soldAll = $dw->table('fact_penjualan as f')->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')
            ->select('f.produk_key')->selectRaw('SUM(f.jumlah) unit, MAX(w.tanggal) terakhir')
            ->groupBy('f.produk_key')->get()->keyBy('produk_key');
        $boughtAll = $dw->table('fact_pembelian')->select('produk_key')->selectRaw('SUM(jumlah) unit')
            ->groupBy('produk_key')->pluck('unit', 'produk_key');
        $batas = $lastDate ? date('Y-m-d', strtotime($lastDate.' -90 days')) : null;
        $slowVariants = $dw->table('dim_produk')->get()->map(function ($p) use ($soldAll, $boughtAll, $lastDate) {
            $s = $soldAll[$p->produk_key] ?? null;
            $p->terjual = (int) ($s->unit ?? 0);
            $p->sisa = (int) ($boughtAll[$p->produk_key] ?? 0) - $p->terjual;
            $p->terakhir = $s->terakhir ?? null;
            $p->diam_hari = $p->terakhir && $lastDate ? (int) round((strtotime($lastDate) - strtotime($p->terakhir)) / 86400) : null;

            return $p;
        })->filter(fn ($p) => $p->sisa > 0 && $batas && ($p->terakhir === null || $p->terakhir <= $batas))
            ->sortByDesc('diam_hari')->values();

        // ---------------- V4 pivot ukuran x warna ----------------
        $pivotRows = $scope($sales()->join('dim_produk as p', 'p.produk_key', '=', 'f.produk_key'))
            ->select('p.ukuran', 'p.warna')->selectRaw('SUM(f.jumlah) unit')->groupBy('p.ukuran', 'p.warna')->get();
        $sizeOrder = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        $sizes = $pivotRows->pluck('ukuran')->unique()
            ->sortBy(fn ($u) => ($i = array_search($u, $sizeOrder, true)) === false ? 99 : $i)->values();
        $colors = $pivotRows->groupBy('warna')->map->sum('unit')->sortDesc()->keys()->values();
        $pivot = [];
        foreach ($pivotRows as $r) {
            $pivot[$r->ukuran][$r->warna] = (int) $r->unit;
        }
        $pivotMax = max([1, ...$pivotRows->pluck('unit')->map(fn ($v) => (int) $v)->all()]);

        // ---------------- V5 promo ----------------
        $promoAgg = $sales()->join('dim_promo as pr', 'pr.promo_key', '=', 'f.promo_key')
            ->select('pr.promo_key', 'pr.nama_promo', 'pr.persen_diskon')
            ->selectRaw('COUNT(DISTINCT f.id_penjualan) trx, SUM(f.pendapatan_bersih) omzet,
                SUM(f.diskon_promo) diskon, SUM(f.laba_kotor) laba')
            ->groupBy('pr.promo_key', 'pr.nama_promo', 'pr.persen_diskon')->get();
        $promoDays = $time($dw->table('dim_promo as pr')
            ->join('dim_waktu as w', fn ($j) => $j->on('w.tanggal', '>=', 'pr.tanggal_mulai')->on('w.tanggal', '<=', 'pr.tanggal_selesai')))
            ->select('pr.promo_key')->selectRaw('COUNT(*) hari')->groupBy('pr.promo_key')->pluck('hari', 'promo_key');
        $periodDays = (int) $time($dw->table('dim_waktu as w'))->count();
        $promos = $promoAgg->map(fn ($r) => (object) [
            'nama'     => $r->nama_promo,
            'persen'   => (float) $r->persen_diskon,
            'tanpa'    => (int) $r->promo_key === 0,
            'trx'      => (int) $r->trx,
            'omzet'    => (float) $r->omzet,
            'diskon'   => (float) $r->diskon,
            'margin'   => $r->omzet > 0 ? $r->laba / $r->omzet : null,
            'hari'     => (int) $r->promo_key === 0 ? $periodDays : (int) ($promoDays[$r->promo_key] ?? 0),
        ])->map(function ($p) {
            $p->omzet_per_hari = $p->hari > 0 ? $p->omzet / $p->hari : null;

            return $p;
        })->sortBy([['tanpa', 'desc'], ['margin', 'desc']])->values();

        // ---------------- V6 member vs guest per kanal + poin ----------------
        $members = $sales()->join('dim_pelanggan as c', 'c.pelanggan_key', '=', 'f.pelanggan_key')
            ->join('dim_transaksi as t', 't.transaksi_key', '=', 'f.transaksi_key')
            ->select('t.kanal_penjualan', 'c.tipe_pelanggan')
            ->selectRaw('COUNT(DISTINCT f.id_penjualan) trx, SUM(f.pendapatan_bersih) omzet')
            ->groupBy('t.kanal_penjualan', 'c.tipe_pelanggan')->get();
        $poin = $sales()->selectRaw('COALESCE(SUM(f.poin_didapat),0) didapat, COALESCE(SUM(f.poin_ditukar),0) ditukar,
                COALESCE(SUM(f.diskon_poin),0) diskon')->first();
        $topMembers = $sales()->join('dim_pelanggan as c', 'c.pelanggan_key', '=', 'f.pelanggan_key')
            ->where('c.tipe_pelanggan', 'MEMBER')
            ->select('c.id_akun', 'c.nama')
            ->selectRaw('COUNT(DISTINCT f.id_penjualan) trx, SUM(f.pendapatan_bersih) omzet,
                SUM(f.poin_didapat) - SUM(f.poin_ditukar) saldo_poin, MAX(w.tanggal) terakhir')
            ->groupBy('c.id_akun', 'c.nama')->orderByDesc('saldo_poin')->limit(8)->get();

        // ---------------- V7 pola hari x kanal ----------------
        $days = $sales()->join('dim_transaksi as t', 't.transaksi_key', '=', 'f.transaksi_key')
            ->select('w.hari_dalam_minggu', 'w.nama_hari', 't.kanal_penjualan')
            ->selectRaw('COUNT(DISTINCT f.id_penjualan) trx')
            ->groupBy('w.hari_dalam_minggu', 'w.nama_hari', 't.kanal_penjualan')->orderBy('w.hari_dalam_minggu')->get();
        $dayLabels = $days->pluck('nama_hari')->unique()->values();
        $dayCount = $time($dw->table('dim_waktu as w'))->select('w.hari_dalam_minggu')->selectRaw('COUNT(*) n')
            ->groupBy('w.hari_dalam_minggu')->pluck('n', 'hari_dalam_minggu');
        $daySeries = [];
        foreach (['ONLINE', 'OFFLINE'] as $kanal) {
            $daySeries[$kanal] = $days->where('kanal_penjualan', $kanal)->groupBy('hari_dalam_minggu')
                ->map(fn ($g, $h) => round($g->sum('trx') / max(1, (int) ($dayCount[$h] ?? 1)), 1))->values()->all();
        }

        // ---------------- V8 uang keluar ----------------
        $suppliers = $buys()->join('dim_supplier as sp', 'sp.supplier_key', '=', 'b.supplier_key')
            ->select('sp.nama_supplier', 'sp.kota')
            ->selectRaw('SUM(b.total_biaya) total, SUM(b.jumlah) unit')
            ->groupBy('sp.nama_supplier', 'sp.kota')->orderByDesc('total')->get();
        $expenses = $costs()->select('e.jenis_pengeluaran')->selectRaw('SUM(e.nominal) total')
            ->groupBy('e.jenis_pengeluaran')->orderByDesc('total')->get();

        // ---------------- metadata & insight (dihitung dari data, tidak ditulis manual) ----------------
        $tahunList = $dw->table('fact_penjualan as f')->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')
            ->distinct()->orderBy('w.tahun')->pluck('w.tahun');
        $etl = Schema::connection('dw')->hasTable('etl_validasi')
            ? $dw->table('etl_validasi')->selectRaw("MAX(dijalankan_pada) waktu, SUM(CASE WHEN status = 'PASS' THEN 1 ELSE 0 END) lulus, COUNT(*) total")->first()
            : null;

        $insights = $this->insights($kpi, $trend, $cash, $promos, $members, $poin, $daySeries, $dayLabels, $slowVariants);

        return compact('kpi', 'trend', 'cash', 'products', 'productLevel', 'productTitle', 'sizes', 'colors', 'pivot',
            'pivotMax', 'promos', 'members', 'poin', 'topMembers', 'dayLabels', 'daySeries', 'suppliers', 'expenses',
            'tahunList', 'etl', 'insights', 'lastDate', 'slowVariants');
    }

    private function insights(array $kpi, Collection $trend, Collection $cash, Collection $promos, Collection $members,
        object $poin, array $daySeries, Collection $dayLabels, Collection $slowVariants): array
    {
        $rp = fn ($v) => 'Rp'.number_format($v, 0, ',', '.');
        $pct = fn ($v) => number_format($v * 100, 1, ',', '.').'%';
        $out = [];

        if ($trend->count() > 1) {
            $hi = $trend->sortByDesc('omzet')->first();
            $lo = $trend->sortBy('omzet')->first();
            $out['tren'] = "Omzet tertinggi {$hi->label} ({$rp($hi->omzet)}), terendah {$lo->label} ({$rp($lo->omzet)}).";
        }
        $out['kas'] = 'Laba operasional '.$rp($kpi['laba_operasional']).' vs arus kas '.$rp($kpi['arus_kas'])
            .'. Selisih '.$rp($kpi['stok_bertambah']).' adalah pembelian yang belum menjadi HPP, yaitu uang yang tertahan di stok.';
        $minus = $cash->filter(fn ($c) => $c->arus_kas < 0);
        if ($minus->isNotEmpty()) {
            $out['kas'] .= ' Arus kas negatif pada '.$minus->pluck('label')->implode(', ').'.';
        }
        $base = $promos->firstWhere('tanpa', true);
        $worst = $promos->where('tanpa', false)->filter(fn ($p) => $p->margin !== null)->sortBy('margin')->first();
        if ($base && $worst) {
            $out['promo'] = 'Margin tanpa promo '.$pct($base->margin).'; margin promo terendah '.$worst->nama
                .' ('.$worst->persen.'% diskon) hanya '.$pct($worst->margin).'.';
        }
        $guest = $members->where('kanal_penjualan', 'OFFLINE')->where('tipe_pelanggan', 'GUEST')->sum('trx');
        $offline = $members->where('kanal_penjualan', 'OFFLINE')->sum('trx');
        if ($offline > 0) {
            $out['member'] = number_format($guest, 0, ',', '.').' dari '.number_format($offline, 0, ',', '.')
                .' transaksi toko ('.$pct($guest / $offline).') dilakukan guest.';
        }
        if ($poin->didapat > 0) {
            $out['member'] = ($out['member'] ?? '').' Poin yang ditukar baru '.$pct($poin->ditukar / $poin->didapat).' dari poin yang didapat.';
        }
        $total = array_map(null, $daySeries['ONLINE'] ?? [], $daySeries['OFFLINE'] ?? []);
        if ($total && $dayLabels->count() === count($total)) {
            $sum = array_map(fn ($p) => ($p[0] ?? 0) + ($p[1] ?? 0), $total);
            $idx = array_keys($sum, max($sum))[0];
            $out['hari'] = 'Rata-rata transaksi per hari tertinggi pada '.$dayLabels[$idx].' ('.number_format(max($sum), 1, ',', '.')
                .'), terendah '.number_format(min($sum), 1, ',', '.').' per hari.';
        }
        if ($slowVariants->isNotEmpty()) {
            $out['produk'] = $slowVariants->count().' varian masih punya sisa stok ('
                .number_format($slowVariants->sum('sisa'), 0, ',', '.').' unit) tetapi tidak terjual 90 hari atau lebih.';
        }

        return $out;
    }
}

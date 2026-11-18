@extends('layouts.admin')
@section('title', 'Analitik Data Warehouse (OLAP)')
@section('content')
    <style>
        .dw-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            align-items: end;
        }
        .dw-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .dw-chart-box {
            position: relative;
            height: 290px;
            width: 100%;
            margin-top: 10px;
        }
        .dw-chart-box.sm {
            height: 240px;
        }
        @media (max-width: 960px) {
            .dw-filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .dw-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h1>Dashboard Analitik Data Warehouse (OLAP)</h1>
            <p class="muted">
                Membaca langsung dari koneksi <code>dw</code> (<code>umkm_dw</code>) — Star Schema
                <code>fact_penjualan</code>, <code>fact_pembelian</code>, <code>fact_pengeluaran</code>, dan <code>fact_stok_snapshot</code>.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.analitik.sync') }}">
            @csrf
            <button type="submit" class="btn green">
                ↻ Refresh Data Warehouse (ETL)
            </button>
        </form>
    </div>

    @if(!$dwAvailable)
        <div class="card">
            <h3 style="margin-top:0">Data Warehouse Belum Siap</h3>
            <p class="muted">
                Database <code>umkm_dw</code> belum termigrasi atau belum memiliki tabel star schema.
                Klik tombol <b>Refresh Data Warehouse (ETL)</b> di kanan atas untuk membuat tabel dan memuat seluruh data dari OLTP.
                @if(!empty($dwError))
                    <br><small>Detail: {{ $dwError }}</small>
                @endif
            </p>
        </div>
    @else
        {{-- Filter Slice & Dice (RMD 9.3) --}}
        <form method="GET" action="{{ route('admin.analitik') }}" class="card" style="margin-bottom:20px;">
            <div class="dw-filter-grid">
                <div>
                    <label for="f-tahun">Tahun (dim_waktu)</label>
                    <select id="f-tahun" name="tahun">
                        <option value="">Semua Tahun</option>
                        @foreach($options['tahunList'] as $thn)
                            <option value="{{ $thn }}" @selected(($filters['tahun'] ?? null) == $thn)>{{ $thn }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f-kuartal">Kuartal (dim_waktu)</label>
                    <select id="f-kuartal" name="kuartal">
                        <option value="">Semua Kuartal</option>
                        @for($q = 1; $q <= 4; $q++)
                            <option value="{{ $q }}" @selected(($filters['kuartal'] ?? null) === $q)>Q{{ $q }} (Kuartal {{ $q }})</option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label for="f-kategori">Kategori (dim_produk)</label>
                    <select id="f-kategori" name="kategori">
                        <option value="">Semua Kategori</option>
                        @foreach($options['kategoriList'] as $kat)
                            <option value="{{ $kat }}" @selected(($filters['kategori'] ?? '') === $kat)>{{ $kat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f-kanal">Kanal (dim_transaksi)</label>
                    <select id="f-kanal" name="kanal">
                        <option value="">Semua Kanal</option>
                        <option value="ONLINE" @selected(($filters['kanal'] ?? '') === 'ONLINE')>ONLINE</option>
                        <option value="OFFLINE" @selected(($filters['kanal'] ?? '') === 'OFFLINE')>OFFLINE (POS)</option>
                    </select>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                <a href="{{ route('admin.analitik') }}" class="btn sm light">Reset Filter</a>
                <button type="submit" class="btn sm">Terapkan Slice &amp; Dice</button>
            </div>
        </form>

        {{-- KPI Cards (Q1, Q5, Q9, Q10, Q11) --}}
        <div class="stats">
            <div class="card stat">
                Omzet Bersih (DW)
                <strong>Rp{{ number_format($kpi['omzet'], 0, ',', '.') }}</strong>
                <small class="muted">
                    Diskon promo: Rp{{ number_format($kpi['diskon_promo'], 0, ',', '.') }} · Poin: Rp{{ number_format($kpi['diskon_poin'], 0, ',', '.') }}
                </small>
            </div>

            <div class="card stat">
                Laba Kotor (Omzet − HPP)
                <strong>Rp{{ number_format($kpi['laba_kotor'], 0, ',', '.') }}</strong>
                <small class="muted">HPP: Rp{{ number_format($kpi['hpp'], 0, ',', '.') }}</small>
            </div>

            <div class="card stat">
                Transaksi &amp; Rata-rata Order
                <strong>{{ number_format($kpi['jumlah_transaksi'], 0, ',', '.') }} trx</strong>
                <small class="muted">
                    {{ number_format($kpi['jumlah_unit'], 0, ',', '.') }} pcs · AOV Rp{{ number_format($kpi['rata_rata_transaksi'], 0, ',', '.') }}
                </small>
            </div>

            <div class="card stat">
                Arus Kas (Omzet − Pengeluaran)
                <strong>Rp{{ number_format($kpi['arus_kas'], 0, ',', '.') }}</strong>
                <small class="muted">
                    Keluar: Rp{{ number_format($kpi['total_pengeluaran'], 0, ',', '.') }} (Restock Rp{{ number_format($kpi['total_pembelian'], 0, ',', '.') }})
                </small>
            </div>
        </div>

        {{-- Q1 & Q11: Tren Omzet/Laba Bulanan + Arus Kas Bulanan --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Tren Omzet &amp; Laba Kotor per Bulan (Q1 · Roll-up)</h3>
                @if($monthlyTrend->isEmpty())
                    <p class="muted">Belum ada data pada filter yang dipilih.</p>
                @else
                    <div class="dw-chart-box">
                        <canvas id="chartMonthlyTrend"></canvas>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Arus Kas Bulanan: Pemasukan vs Pengeluaran (Q11 · Drill-across)</h3>
                @if($monthlyCashFlow->isEmpty())
                    <p class="muted">Belum ada data arus kas pada filter yang dipilih.</p>
                @else
                    <div class="dw-chart-box">
                        <canvas id="chartCashFlow"></canvas>
                    </div>
                @endif
            </div>
        </div>

        {{-- Q2 & Q8: Omzet per Kategori + Hari Teramai --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Omzet &amp; Laba per Kategori (Q2 · Drill-down)</h3>
                @if($categorySales->isEmpty())
                    <p class="muted">Belum ada data kategori.</p>
                @else
                    <div class="dw-chart-box">
                        <canvas id="chartCategory"></canvas>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Hari Teramai dalam Seminggu (Q8 · dim_waktu)</h3>
                @if($busiestDays->isEmpty())
                    <p class="muted">Belum ada data harian.</p>
                @else
                    <div class="dw-chart-box">
                        <canvas id="chartBusiestDays"></canvas>
                    </div>
                @endif
            </div>
        </div>

        {{-- Q4 & Q6: Kanal/Metode Bayar + Member vs Guest --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Kanal Penjualan &amp; Metode Pembayaran (Q4 · dim_transaksi)</h3>
                @if($channelPayment->isEmpty())
                    <p class="muted">Belum ada data transaksi.</p>
                @else
                    <div class="dw-chart-box sm">
                        <canvas id="chartChannelPayment"></canvas>
                    </div>
                    <div class="table-wrap" style="margin-top:14px;">
                        <table>
                            <tr>
                                <th>Kanal</th>
                                <th>Metode</th>
                                <th>Transaksi</th>
                                <th>Unit</th>
                                <th>Omzet</th>
                            </tr>
                            @foreach($channelPayment as $cp)
                                <tr>
                                    <td><b>{{ $cp->kanal_penjualan }}</b></td>
                                    <td>{{ $cp->metode_pembayaran }}</td>
                                    <td>{{ number_format($cp->transaksi, 0, ',', '.') }}</td>
                                    <td>{{ number_format($cp->qty, 0, ',', '.') }} pcs</td>
                                    <td>Rp{{ number_format($cp->omzet, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Porsi Member vs Guest (Q6 · dim_customer)</h3>
                @if($memberVsGuest->isEmpty())
                    <p class="muted">Belum ada data customer.</p>
                @else
                    <div class="dw-chart-box sm">
                        <canvas id="chartMemberGuest"></canvas>
                    </div>
                    <div class="table-wrap" style="margin-top:14px;">
                        <table>
                            <tr>
                                <th>Segmentasi</th>
                                <th>Transaksi</th>
                                <th>Unit</th>
                                <th>Omzet</th>
                            </tr>
                            @foreach($memberVsGuest as $mg)
                                <tr>
                                    <td><b>{{ $mg->tipe_pembeli }}</b></td>
                                    <td>{{ number_format($mg->transaksi, 0, ',', '.') }}</td>
                                    <td>{{ number_format($mg->qty, 0, ',', '.') }} pcs</td>
                                    <td>Rp{{ number_format($mg->omzet, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Q2 Top 10 Produk & Q3 Ukuran x Warna --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Top 10 Produk Terlaris (Q2)</h3>
                @if($topProducts->isEmpty())
                    <p class="muted">Belum ada data produk.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th>Unit</th>
                                <th>Omzet</th>
                                <th>Laba</th>
                            </tr>
                            @foreach($topProducts as $tp)
                                <tr>
                                    <td><b>{{ $tp->nama_produk }}</b></td>
                                    <td>{{ $tp->nama_kategori }}</td>
                                    <td>{{ number_format($tp->qty, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($tp->omzet, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($tp->laba, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Kombinasi Ukuran × Warna Terlaris (Q3 · Pivot)</h3>
                @if($sizeColorMatrix->isEmpty())
                    <p class="muted">Belum ada data varian.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Ukuran</th>
                                <th>Warna</th>
                                <th>Unit Terjual</th>
                                <th>Omzet</th>
                            </tr>
                            @foreach($sizeColorMatrix as $sc)
                                <tr>
                                    <td><span class="badge">{{ $sc->ukuran }}</span></td>
                                    <td>{{ ucfirst($sc->warna) }}</td>
                                    <td><b>{{ number_format($sc->qty, 0, ',', '.') }} pcs</b></td>
                                    <td>Rp{{ number_format($sc->omzet, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Q5 Efektivitas Promo & Q10 Pengeluaran per Jenis --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Efektivitas Promo (Q5 · dim_promo)</h3>
                @if($promoPerf->isEmpty())
                    <p class="muted">Belum ada data promo.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Promo</th>
                                <th>Kelompok</th>
                                <th>Unit</th>
                                <th>Omzet</th>
                                <th>Total Diskon</th>
                            </tr>
                            @foreach($promoPerf as $pr)
                                <tr>
                                    <td><b>{{ $pr->nama_promo }}</b></td>
                                    <td><span class="badge">{{ $pr->kelompok_diskon }}</span></td>
                                    <td>{{ number_format($pr->qty, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($pr->omzet, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($pr->total_diskon, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Komposisi Pengeluaran (Q10 · fact_pengeluaran)</h3>
                @if($expenseByType->isEmpty())
                    <p class="muted">Belum ada data pengeluaran.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Jenis Pengeluaran</th>
                                <th>Kelompok</th>
                                <th>Catat</th>
                                <th>Nominal</th>
                            </tr>
                            @foreach($expenseByType as $ex)
                                <tr>
                                    <td><b>{{ $ex->jenis_pengeluaran }}</b></td>
                                    <td>
                                        <span class="badge {{ $ex->kelompok === 'OPERASIONAL' ? 'warn' : 'ok' }}">
                                            {{ $ex->kelompok }}
                                        </span>
                                    </td>
                                    <td>{{ $ex->jumlah_catat }}×</td>
                                    <td>Rp{{ number_format($ex->total_nominal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Q6 Top Customer, Q7 Kinerja Kasir, Q9 Supplier, Q12 Snapshot Stok --}}
        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Top Customer Member (Q6)</h3>
                @if($topCustomers->isEmpty())
                    <p class="muted">Belum ada transaksi member.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Customer</th>
                                <th>Trx</th>
                                <th>Unit</th>
                                <th>Total Belanja</th>
                            </tr>
                            @foreach($topCustomers as $c)
                                <tr>
                                    <td>
                                        <b>{{ $c->nama }}</b>
                                        <br><small class="muted">{{ $c->id_akun }}</small>
                                    </td>
                                    <td>{{ $c->transaksi }}</td>
                                    <td>{{ $c->qty }}</td>
                                    <td>Rp{{ number_format($c->omzet, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Kinerja Kasir POS (Q7 · dim_staf)</h3>
                @if($cashierPerf->isEmpty())
                    <p class="muted">Belum ada transaksi kasir offline.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Kasir</th>
                                <th>Transaksi</th>
                                <th>Unit</th>
                                <th>Omzet</th>
                            </tr>
                            @foreach($cashierPerf as $ks)
                                <tr>
                                    <td>
                                        <b>{{ $ks->nama }}</b>
                                        <br><small class="muted">{{ $ks->id_akun }}</small>
                                    </td>
                                    <td>{{ number_format($ks->transaksi, 0, ',', '.') }}</td>
                                    <td>{{ number_format($ks->qty, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($ks->omzet, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="dw-grid-2">
            <div class="card">
                <h3 style="margin-top:0">Pembelian per Supplier (Q9 · fact_pembelian)</h3>
                @if($supplierPerf->isEmpty())
                    <p class="muted">Belum ada data pembelian DITERIMA.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Supplier</th>
                                <th>PO</th>
                                <th>Unit</th>
                                <th>Total Biaya</th>
                            </tr>
                            @foreach($supplierPerf as $sp)
                                <tr>
                                    <td>
                                        <b>{{ $sp->nama_supplier }}</b>
                                        <br><small class="muted">{{ $sp->id_supplier }}</small>
                                    </td>
                                    <td>{{ $sp->jumlah_po }}</td>
                                    <td>{{ number_format($sp->qty, 0, ',', '.') }} pcs</td>
                                    <td>Rp{{ number_format($sp->total_biaya, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h3 style="margin-top:0">Snapshot Stok di Bawah Minimum (Q12 · fact_stok_snapshot)</h3>
                @if($lowStockSnapshot->isEmpty())
                    <p class="muted">Tidak ada varian yang berada di bawah stok minimum pada snapshot terakhir.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <tr>
                                <th>Produk &amp; Varian</th>
                                <th>Stok</th>
                                <th>Minimum</th>
                            </tr>
                            @foreach($lowStockSnapshot as $sn)
                                <tr>
                                    <td>
                                        <b>{{ $sn->nama_produk }}</b>
                                        <br><small class="muted">{{ $sn->nama_kategori }} · {{ $sn->ukuran }} / {{ ucfirst($sn->warna) }}</small>
                                    </td>
                                    <td><span class="badge bad">{{ $sn->stok }}</span></td>
                                    <td>{{ $sn->stok_minimum }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Chart.js (RMD Bagian 3 & 9) --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') return;

                const formatRp = (val) => 'Rp' + Number(val || 0).toLocaleString('id-ID');

                @if($monthlyTrend->isNotEmpty())
                new Chart(document.getElementById('chartMonthlyTrend'), {
                    type: 'line',
                    data: {
                        labels: @json($monthlyTrend->map(fn($r) => substr($r->nama_bulan, 0, 3).' '.$r->tahun)),
                        datasets: [
                            {
                                label: 'Omzet Bersih',
                                data: @json($monthlyTrend->pluck('omzet')),
                                borderColor: '#238253',
                                backgroundColor: 'rgba(35, 130, 83, 0.12)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'Laba Kotor',
                                data: @json($monthlyTrend->pluck('laba')),
                                borderColor: '#a6741c',
                                backgroundColor: 'rgba(166, 116, 28, 0.10)',
                                tension: 0.3,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + formatRp(ctx.raw) } } }
                    }
                });
                @endif

                @if($monthlyCashFlow->isNotEmpty())
                new Chart(document.getElementById('chartCashFlow'), {
                    type: 'bar',
                    data: {
                        labels: @json($monthlyCashFlow->map(fn($r) => substr($r->nama_bulan, 0, 3).' '.$r->tahun)),
                        datasets: [
                            {
                                label: 'Pemasukan (Penjualan)',
                                data: @json($monthlyCashFlow->pluck('pemasukan')),
                                backgroundColor: 'rgba(35, 130, 83, 0.78)'
                            },
                            {
                                label: 'Pengeluaran',
                                data: @json($monthlyCashFlow->pluck('pengeluaran')),
                                backgroundColor: 'rgba(185, 74, 72, 0.78)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + formatRp(ctx.raw) } } }
                    }
                });
                @endif

                @if($categorySales->isNotEmpty())
                new Chart(document.getElementById('chartCategory'), {
                    type: 'bar',
                    data: {
                        labels: @json($categorySales->pluck('nama_kategori')),
                        datasets: [
                            {
                                label: 'Omzet',
                                data: @json($categorySales->pluck('omzet')),
                                backgroundColor: '#1d1d1b'
                            },
                            {
                                label: 'Laba Kotor',
                                data: @json($categorySales->pluck('laba')),
                                backgroundColor: '#238253'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + formatRp(ctx.raw) } } }
                    }
                });
                @endif

                @if($busiestDays->isNotEmpty())
                new Chart(document.getElementById('chartBusiestDays'), {
                    type: 'bar',
                    data: {
                        labels: @json($busiestDays->pluck('nama_hari')),
                        datasets: [{
                            label: 'Jumlah Transaksi',
                            data: @json($busiestDays->pluck('transaksi')),
                            backgroundColor: '#a6741c'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                @endif

                @if($channelPayment->isNotEmpty())
                new Chart(document.getElementById('chartChannelPayment'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($channelPayment->map(fn($r) => $r->kanal_penjualan.' - '.$r->metode_pembayaran)),
                        datasets: [{
                            data: @json($channelPayment->pluck('omzet')),
                            backgroundColor: ['#238253', '#a6741c', '#1d1d1b', '#77736d']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + formatRp(ctx.raw) } } }
                    }
                });
                @endif

                @if($memberVsGuest->isNotEmpty())
                new Chart(document.getElementById('chartMemberGuest'), {
                    type: 'doughnut',
                    data: {
                        labels: @json($memberVsGuest->pluck('tipe_pembeli')),
                        datasets: [{
                            data: @json($memberVsGuest->pluck('omzet')),
                            backgroundColor: ['#238253', '#a6741c']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + formatRp(ctx.raw) } } }
                    }
                });
                @endif
            });
        </script>
    @endif
@endsection

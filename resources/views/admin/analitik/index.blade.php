@extends('layouts.admin')
@section('title', 'Analitik Data Warehouse')
@section('content')
@php
    $rp = fn ($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
    $num = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');
    $pct = fn ($v) => $v === null ? '–' : number_format($v * 100, 1, ',', '.') . '%';
    $q = fn (array $extra) => request()->fullUrlWithQuery($extra);
@endphp
<style>
    .dw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
    .dw-section { margin: 34px 0 6px; padding-bottom: 6px; border-bottom: 2px solid #e5e7eb; }
    .dw-section h2 { margin: 0; font-size: 1.15em; }
    .dw-section p { margin: 4px 0 0; }
    .dw-chart { position: relative; height: 280px; margin-top: 10px; }
    .dw-note { background: #f8fafc; border-left: 4px solid #2563eb; padding: 9px 12px; margin-top: 12px; font-size: .9em; }
    .dw-note.warn { border-left-color: #d97706; background: #fffbeb; }
    .dw-q { font-size: .82em; color: #6b7280; margin: 2px 0 0; }
    .dw-kpi small { display: block; margin-top: 4px; }
    .dw-heat td { text-align: center; }
    .dw-tag { font-size: .75em; padding: 2px 6px; border-radius: 4px; background: #eef2ff; color: #3730a3; }
    .dw-crumb a { text-decoration: none; }
    @media (max-width: 960px) { .dw-grid-2 { grid-template-columns: 1fr; } }
</style>

<div class="page-head">
    <div>
        <h1>Dashboard Analitik FORMA</h1>
        <p class="muted">
            Sumber: Data Warehouse <code>umkm_dw</code> (3 fakta: penjualan, pembelian, pengeluaran operasional).
            @if($dwAvailable && $etl && $etl->waktu)
                Dimuat Pentaho {{ \Illuminate\Support\Carbon::parse($etl->waktu)->format('d M Y H:i') }} ·
                validasi ETL <b>{{ (int) $etl->lulus }}/{{ (int) $etl->total }} PASS</b>
            @endif
        </p>
    </div>
</div>

@if(!$dwAvailable)
    <div class="card">
        <h3 style="margin-top:0">Data Warehouse belum siap</h3>
        <p class="muted">{{ $dwError }}</p>
        <ol class="muted">
            <li>Buat skema: <code>php artisan migrate --database=dw --path=database/migrations/dw</code></li>
            <li>Muat data: jalankan job Pentaho <code>database/dw/pentaho/job_etl_umkm_dw.kjb</code> (lihat docs/SETUP_AND_RUN.md)</li>
        </ol>
    </div>
@else
    {{-- Filter global: hanya periode, karena ketiga fakta memiliki dim_waktu --}}
    <form method="GET" class="card" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
        <div>
            <label for="f-tahun">Tahun</label>
            <select id="f-tahun" name="tahun">
                <option value="">Semua</option>
                @foreach($tahunList as $t)
                    <option value="{{ $t }}" @selected($filters['tahun'] == $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-kuartal">Kuartal</label>
            <select id="f-kuartal" name="kuartal">
                <option value="">Semua</option>
                @for($k = 1; $k <= 4; $k++)
                    <option value="{{ $k }}" @selected($filters['kuartal'] === $k)>Q{{ $k }}</option>
                @endfor
            </select>
        </div>
        <button class="btn sm" type="submit">Terapkan</button>
        <a class="btn sm light" href="{{ route('admin.analitik') }}">Reset</a>
        <span class="muted" style="font-size:.85em">Filter periode berlaku untuk semua visual (slice pada dimensi Waktu).</span>
    </form>

    {{-- KPI --}}
    <div class="stats" style="margin-top:20px">
        <div class="card stat dw-kpi">Omzet
            <strong>{{ $rp($kpi['omzet']) }}</strong>
            <small class="muted">{{ $num($kpi['transaksi']) }} transaksi · rata-rata {{ $rp($kpi['aov']) }}</small>
        </div>
        <div class="card stat dw-kpi">Laba kotor
            <strong>{{ $rp($kpi['laba_kotor']) }}</strong>
            <small class="muted">Margin {{ $pct($kpi['margin']) }} · HPP {{ $rp($kpi['hpp']) }}</small>
        </div>
        <div class="card stat dw-kpi">Laba operasional
            <strong>{{ $rp($kpi['laba_operasional']) }}</strong>
            <small class="muted">Laba kotor − biaya operasional {{ $rp($kpi['operasional']) }}</small>
        </div>
        <div class="card stat dw-kpi">Arus kas operasional
            <strong style="color:{{ $kpi['arus_kas'] < 0 ? '#b91c1c' : 'inherit' }}">{{ $rp($kpi['arus_kas']) }}</strong>
            <small class="muted">Omzet − pembelian {{ $rp($kpi['pembelian']) }} − biaya</small>
        </div>
    </div>

    {{-- ================= A. KESEHATAN USAHA ================= --}}
    <div class="dw-section">
        <h2>A. Kesehatan usaha</h2>
        <p class="muted">Apakah usaha tumbuh, dan kenapa kas berbeda dari laba?</p>
    </div>
    <div class="dw-grid-2">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <h3 style="margin:0">V1. Tren omzet dan laba kotor</h3>
                    <p class="dw-q">fact_penjualan × dim_waktu · roll-up / drill-down</p>
                </div>
                <div>
                    <a class="btn sm {{ $filters['level'] === 'bulan' ? '' : 'light' }}" href="{{ $q(['level' => 'bulan']) }}">Bulan</a>
                    <a class="btn sm {{ $filters['level'] === 'kuartal' ? '' : 'light' }}" href="{{ $q(['level' => 'kuartal']) }}">Kuartal</a>
                </div>
            </div>
            <div class="dw-chart"><canvas id="cTrend"></canvas></div>
            @isset($insights['tren'])<div class="dw-note">{{ $insights['tren'] }}</div>@endisset
        </div>
        <div class="card">
            <h3 style="margin:0">V2. Uang masuk vs uang keluar per bulan</h3>
            <p class="dw-q">fact_penjualan + fact_pembelian + fact_pengeluaran · drill-across lewat dim_waktu</p>
            <div class="dw-chart"><canvas id="cCash"></canvas></div>
            <div class="dw-note warn">{{ $insights['kas'] }}</div>
        </div>
    </div>

    {{-- ================= B. PRODUK & STOK ================= --}}
    <div class="dw-section">
        <h2>B. Produk dan stok</h2>
        <p class="muted">Produk mana yang laku dan untung, mana yang menumpuk, dan ukuran/warna apa yang dicari?</p>
    </div>
    <div class="card" style="margin-top:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <div>
                <h3 style="margin:0">V3. Kinerja produk: {{ $productTitle }}</h3>
                <p class="dw-q">fact_penjualan + fact_pembelian × dim_produk · drill-down Kategori → Produk → Varian, drill-across</p>
                <p class="dw-crumb" style="margin:6px 0 0">
                    <a href="{{ $q(['kat' => null, 'produk' => null]) }}">Semua kategori</a>
                    @if($filters['kat'] !== '') › <a href="{{ $q(['produk' => null]) }}">{{ $filters['kat'] }}</a>@endif
                    @if($filters['produk'] !== '') › {{ $products->isNotEmpty() ? $productTitle : $filters['produk'] }}@endif
                </p>
            </div>
            <div>
                <a class="btn sm {{ $filters['urut'] === 'tertinggi' ? '' : 'light' }}" href="{{ $q(['urut' => 'tertinggi']) }}">Terlaris</a>
                <a class="btn sm {{ $filters['urut'] === 'terendah' ? '' : 'light' }}" href="{{ $q(['urut' => 'terendah']) }}">Paling lambat</a>
            </div>
        </div>
        <div class="table-wrap" style="margin-top:10px">
            <table>
                <tr>
                    <th>{{ ucfirst($productLevel) }}</th><th>Unit terjual</th><th>Omzet</th><th>Margin</th>
                    <th>Unit dibeli</th><th>Dibeli − terjual</th><th>Terakhir terjual</th>
                </tr>
                @foreach($products->take($productLevel === 'kategori' ? 50 : 15) as $p)
                    <tr>
                        <td>
                            @if($productLevel === 'kategori')
                                <a href="{{ $q(['kat' => $p->key, 'produk' => null]) }}"><b>{{ $p->label }}</b></a>
                            @elseif($productLevel === 'produk')
                                <a href="{{ $q(['produk' => $p->key]) }}"><b>{{ $p->label }}</b></a>
                            @else
                                <b>{{ $p->label }}</b>
                            @endif
                        </td>
                        <td>{{ $num($p->unit) }}</td>
                        <td>{{ $rp($p->omzet) }}</td>
                        <td>{{ $pct($p->margin) }}</td>
                        <td>{{ $num($p->unit_beli) }}</td>
                        <td>{{ $num($p->selisih) }}</td>
                        <td>
                            {{ $p->terakhir ? \Illuminate\Support\Carbon::parse($p->terakhir)->format('d M Y') : 'belum pernah' }}
                            @if($p->diam_hari !== null && $p->diam_hari >= 90)<span class="badge warn">{{ $p->diam_hari }} hari</span>@endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <p class="muted" style="font-size:.82em;margin:8px 0 0">
            "Dibeli − terjual" pada periode terpilih. Untuk seluruh periode, nilainya sama dengan stok OLTP
            (selisih hanya penyesuaian stock opname; lihat validasi V15).
        </p>
        @if($slowVariants->isNotEmpty())
            <div class="dw-note warn">
                <b>{{ $insights['produk'] }}</b>
                <div class="table-wrap" style="margin-top:8px">
                    <table>
                        <tr><th>Varian</th><th>Kategori</th><th>Sisa unit</th><th>Terakhir terjual</th><th>Hari tanpa penjualan</th></tr>
                        @foreach($slowVariants->take(10) as $v)
                            <tr>
                                <td>{{ $v->nama_produk }} <span class="muted">{{ $v->ukuran }} / {{ $v->warna }}</span></td>
                                <td>{{ $v->nama_kategori }}</td>
                                <td>{{ $num($v->sisa) }}</td>
                                <td>{{ $v->terakhir ? \Illuminate\Support\Carbon::parse($v->terakhir)->format('d M Y') : 'belum pernah' }}</td>
                                <td>{{ $v->diam_hari ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="card" style="margin-top:20px">
        <h3 style="margin:0">V4. Unit terjual per ukuran × warna{{ $filters['kat'] !== '' ? ' · ' . $filters['kat'] : '' }}</h3>
        <p class="dw-q">fact_penjualan × dim_produk · pivot (ukuran di baris, warna di kolom); ikut pilihan kategori/produk di V3</p>
        <div class="table-wrap" style="margin-top:10px">
            <table class="dw-heat">
                <tr><th>Ukuran</th>@foreach($colors as $c)<th>{{ $c }}</th>@endforeach<th>Total</th></tr>
                @foreach($sizes as $s)
                    <tr>
                        <th>{{ $s }}</th>
                        @foreach($colors as $c)
                            @php $v = $pivot[$s][$c] ?? 0; $a = round(0.08 + 0.72 * $v / $pivotMax, 2); @endphp
                            <td style="background: rgba(37,99,235,{{ $a }}); color: {{ $a > .45 ? '#fff' : 'inherit' }}">{{ $num($v) }}</td>
                        @endforeach
                        <td><b>{{ $num(array_sum($pivot[$s] ?? [])) }}</b></td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>

    {{-- ================= C. STRATEGI PENJUALAN ================= --}}
    <div class="dw-section">
        <h2>C. Strategi penjualan</h2>
        <p class="muted">Promo mana yang sehat, seberapa besar peran member, dan kapan toko ramai?</p>
    </div>
    <div class="card" style="margin-top:20px">
        <h3 style="margin:0">V5. Efektivitas promo</h3>
        <p class="dw-q">fact_penjualan × dim_promo · slice per promo, dibandingkan dengan "Tanpa promo"</p>
        <div class="dw-chart" style="height:300px"><canvas id="cPromo"></canvas></div>
        <div>
            <div class="table-wrap" style="margin-top:12px">
                <table>
                    <tr><th>Promo</th><th>Diskon</th><th>Transaksi</th><th>Omzet</th><th>Biaya diskon</th><th>Margin</th><th>Omzet/hari aktif</th></tr>
                    @foreach($promos as $p)
                        <tr @if($p->tanpa) style="background:#f1f5f9" @endif>
                            <td>{{ $p->nama }}</td>
                            <td>{{ $p->tanpa ? '–' : $num($p->persen) . '%' }}</td>
                            <td>{{ $num($p->trx) }}</td>
                            <td>{{ $rp($p->omzet) }}</td>
                            <td>{{ $rp($p->diskon) }}</td>
                            <td>{{ $pct($p->margin) }}</td>
                            <td>{{ $p->omzet_per_hari === null ? '–' : $rp($p->omzet_per_hari) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @isset($insights['promo'])<div class="dw-note warn">{{ $insights['promo'] }} Analisis ini deskriptif: tidak membuktikan promo menaikkan penjualan.</div>@endisset
    </div>

    <div class="dw-grid-2">
        <div class="card">
            <h3 style="margin:0">V6. Member vs guest per kanal</h3>
            <p class="dw-q">fact_penjualan × dim_pelanggan × dim_transaksi · dice</p>
            <div class="dw-chart"><canvas id="cMember"></canvas></div>
            <div style="display:flex;gap:18px;margin-top:10px;font-size:.9em">
                <div>Poin didapat<br><b>{{ $num($poin->didapat) }}</b></div>
                <div>Poin ditukar<br><b>{{ $num($poin->ditukar) }}</b></div>
                <div>Diskon poin<br><b>{{ $rp($poin->diskon) }}</b></div>
            </div>
            @isset($insights['member'])<div class="dw-note">{{ $insights['member'] }}</div>@endisset
            <details style="margin-top:10px">
                <summary>Member dengan saldo poin terbesar (drill ke pelanggan)</summary>
                <div class="table-wrap">
                    <table>
                        <tr><th>Member</th><th>Transaksi</th><th>Omzet</th><th>Saldo poin periode</th><th>Terakhir belanja</th></tr>
                        @foreach($topMembers as $m)
                            <tr>
                                <td>{{ $m->nama }} <span class="muted">{{ $m->id_akun }}</span></td>
                                <td>{{ $num($m->trx) }}</td>
                                <td>{{ $rp($m->omzet) }}</td>
                                <td>{{ $num($m->saldo_poin) }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($m->terakhir)->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </details>
        </div>
        <div class="card">
            <h3 style="margin:0">V7. Rata-rata transaksi per hari</h3>
            <p class="dw-q">fact_penjualan × dim_waktu (hari) × dim_transaksi · pivot hari × kanal</p>
            <div class="dw-chart"><canvas id="cDay"></canvas></div>
            @isset($insights['hari'])<div class="dw-note">{{ $insights['hari'] }}</div>@endisset
        </div>
    </div>

    {{-- ================= D. UANG KELUAR ================= --}}
    <div class="dw-section">
        <h2>D. Uang keluar</h2>
        <p class="muted">Ke supplier mana uang pembelian mengalir, dan biaya operasional terbesar di pos apa?</p>
    </div>
    <div class="dw-grid-2">
        <div class="card">
            <h3 style="margin:0">V8a. Pembelian stok per supplier</h3>
            <p class="dw-q">fact_pembelian × dim_supplier</p>
            <div class="dw-chart"><canvas id="cSupplier"></canvas></div>
        </div>
        <div class="card">
            <h3 style="margin:0">V8b. Biaya operasional per jenis</h3>
            <p class="dw-q">fact_pengeluaran (jenis = degenerate dimension). Pembelian stok tidak termasuk.</p>
            <div class="dw-chart"><canvas id="cExpense"></canvas></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        const rp = v => 'Rp' + Math.round(v || 0).toLocaleString('id-ID');
        const rpShort = v => 'Rp' + (Math.abs(v) >= 1e6 ? (v / 1e6).toLocaleString('id-ID', {maximumFractionDigits: 0}) + ' jt' : Math.round(v).toLocaleString('id-ID'));
        const money = { y: { ticks: { callback: rpShort } } };
        const tip = { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + rp(c.raw) } } };

        new Chart(cTrend, { type: 'line', data: {
            labels: @json($trend->pluck('label')),
            datasets: [
                { label: 'Omzet', data: @json($trend->pluck('omzet')), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .3 },
                { label: 'Laba kotor', data: @json($trend->pluck('laba')), borderColor: '#16a34a', tension: .3 }
            ] }, options: { maintainAspectRatio: false, scales: money, plugins: tip } });

        new Chart(cCash, { data: {
            labels: @json($cash->pluck('label')),
            datasets: [
                { type: 'bar', label: 'Uang masuk (omzet)', data: @json($cash->pluck('masuk')), backgroundColor: '#2563eb', stack: 'masuk' },
                { type: 'bar', label: 'Pembelian stok', data: @json($cash->pluck('pembelian')), backgroundColor: '#f59e0b', stack: 'keluar' },
                { type: 'bar', label: 'Biaya operasional', data: @json($cash->pluck('operasional')), backgroundColor: '#ef4444', stack: 'keluar' },
                { type: 'line', label: 'Arus kas', data: @json($cash->pluck('arus_kas')), borderColor: '#111827', tension: .2 }
            ] }, options: { maintainAspectRatio: false, scales: money, plugins: tip } });

        const promos = @json($promos->where('tanpa', false)->values());
        const base = @json($promos->firstWhere('tanpa', true));
        new Chart(cPromo, { type: 'bar', data: {
            labels: promos.map(p => p.nama + ' (' + p.persen + '%)'),
            datasets: [{ label: 'Margin kotor', data: promos.map(p => p.margin === null ? null : +(p.margin * 100).toFixed(1)),
                backgroundColor: promos.map(p => p.persen > 20 ? '#ef4444' : '#f59e0b') }] },
            options: { indexAxis: 'y', maintainAspectRatio: false,
                scales: { x: { min: 0, max: 50, ticks: { callback: v => v + '%' } } },
                plugins: { legend: { display: false }, subtitle: { display: !!base, text: base ? 'Pembanding tanpa promo: ' + (base.margin * 100).toFixed(1) + '%' : '' } } } });

        const members = @json($members);
        const kanal = ['ONLINE', 'OFFLINE'];
        const omzetOf = (k, t) => (members.find(m => m.kanal_penjualan === k && m.tipe_pelanggan === t) || {}).omzet || 0;
        new Chart(cMember, { type: 'bar', data: { labels: kanal, datasets: [
            { label: 'Member', data: kanal.map(k => omzetOf(k, 'MEMBER')), backgroundColor: '#2563eb' },
            { label: 'Guest', data: kanal.map(k => omzetOf(k, 'GUEST')), backgroundColor: '#9ca3af' }
        ] }, options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, ticks: { callback: rpShort } } }, plugins: tip } });

        new Chart(cDay, { type: 'bar', data: { labels: @json($dayLabels), datasets: [
            { label: 'Online', data: @json($daySeries['ONLINE'] ?? []), backgroundColor: '#2563eb' },
            { label: 'Offline', data: @json($daySeries['OFFLINE'] ?? []), backgroundColor: '#16a34a' }
        ] }, options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, title: { display: true, text: 'transaksi per hari' } } } } });

        new Chart(cSupplier, { type: 'bar', data: { labels: @json($suppliers->map(fn ($s) => $s->nama_supplier . ' (' . $s->kota . ')')),
            datasets: [{ label: 'Total pembelian', data: @json($suppliers->pluck('total')), backgroundColor: '#f59e0b' }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, scales: { x: { ticks: { callback: rpShort } } }, plugins: { legend: { display: false }, ...tip } } });

        new Chart(cExpense, { type: 'doughnut', data: { labels: @json($expenses->pluck('jenis_pengeluaran')),
            datasets: [{ data: @json($expenses->pluck('total')), backgroundColor: ['#ef4444', '#f59e0b', '#2563eb', '#16a34a', '#8b5cf6', '#6b7280'] }] },
            options: { maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: c => c.label + ': ' + rp(c.raw) } } } } });
    });
    </script>
@endif
@endsection

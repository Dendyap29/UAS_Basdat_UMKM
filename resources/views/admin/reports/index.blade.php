@extends('layouts.admin')
@section('title', 'Laporan & Analitik DW')
@section('content')
    <style>
        .dw-section {
            margin-top: 32px;
            padding-top: 26px;
            border-top: 2px solid var(--line);
        }
        .dw-filter-bar {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            align-items: end;
            margin-bottom: 20px;
        }
        .dw-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .dw-bar-list {
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }
        .dw-bar-item {
            display: grid;
            gap: 5px;
        }
        .dw-bar-head {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            gap: 8px;
        }
        .dw-bar-track {
            height: 10px;
            background: #f1eee8;
            border-radius: 999px;
            overflow: hidden;
        }
        .dw-bar-fill {
            height: 100%;
            background: #222;
            border-radius: 999px;
        }
        .dw-bar-fill.green {
            background: #238253;
        }
        .dw-bar-fill.amber {
            background: #a6741c;
        }
        .dw-donut-wrap {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .dw-legend {
            display: grid;
            gap: 10px;
            flex: 1;
            min-width: 180px;
        }
        .dw-legend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            padding-bottom: 6px;
            border-bottom: 1px solid #f1eee8;
        }
        .dw-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        @media (max-width: 960px) {
            .dw-filter-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            .dw-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h1>Monitoring & Laporan</h1>
            <p class="muted">
                Lihat ringkasan pemasukan, pengeluaran operasional, stok, dan analitik Data Warehouse.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.reports.sync-dw') }}">
            @csrf
            <button type="submit" class="btn green">
                ↻ Sync Data Warehouse (ETL)
            </button>
        </form>
    </div>

    {{-- ================= 1. RINGKASAN TRANSAKSIONAL (OLTP) ================= --}}
    <div class="stats">
        <div class="card stat">
            Pemasukan
            <strong>Rp{{ number_format($income, 0, ',', '.') }}</strong>
        </div>
        <div class="card stat">
            Pembelian Produk
            <strong>Rp{{ number_format($purchase, 0, ',', '.') }}</strong>
        </div>
        <div class="card stat">
            Pengeluaran Operasional
            <strong>Rp{{ number_format($operational, 0, ',', '.') }}</strong>
        </div>
        <div class="card stat">
            Total Pengeluaran
            <strong>Rp{{ number_format($totalExpense, 0, ',', '.') }}</strong>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <span class="muted">Selisih Arus Keuangan</span>
        <h2 style="margin-bottom:0">Rp{{ number_format($cashFlow, 0, ',', '.') }}</h2>
    </div>

    <div class="dw-grid-2">
        <div class="card">
            <h3 style="margin-top:0">Best Seller (Operasional)</h3>
            @forelse($best as $b)
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;">
                    <span>{{ $b->nama_produk }}</span>
                    <b>{{ $b->qty }} unit</b>
                </div>
            @empty
                <p class="muted">Belum ada data penjualan.</p>
            @endforelse
        </div>

        <div class="card">
            <h3 style="margin-top:0">Stok Hampir Habis / Minimum</h3>
            @forelse($low as $item)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #eee;gap:12px;">
                    <span>
                        {{ $item->nama_produk }}
                        <br>
                        <small class="muted">
                            {{ $item->ukuran }} / {{ ucfirst($item->warna) }} · Min {{ $item->stok_minimum }}
                        </small>
                    </span>
                    @if($item->stok == 0)
                        <span class="badge bad">HABIS (0)</span>
                    @else
                        <span class="badge warn">Stok {{ $item->stok }}</span>
                    @endif
                </div>
            @empty
                <p class="muted">Semua stok varian berada di atas batas minimum.</p>
            @endforelse
        </div>
    </div>

    {{-- ================= 2. INTEGRASI DATA WAREHOUSE (OLAP ANALYTICS) ================= --}}
    <div class="dw-section">
        <div class="page-head">
            <div>
                <h2>Dashboard Analitik Data Warehouse (OLAP)</h2>
                <p class="muted">
                    Analisis multidimensi dari skema bintang (<code>fakta_penjualan</code>, <code>fakta_pembelian_produk</code>, <code>fakta_pengeluaran</code>, dan tabel <code>dim_*</code>).
                </p>
            </div>
        </div>

        @if(!$dwAvailable)
            <div class="card">
                <p class="muted" style="margin:0">
                    Tabel Data Warehouse belum tersedia atau belum disinkronkan. Klik tombol <b>Sync Data Warehouse (ETL)</b> di kanan atas untuk memuat data ke Data Warehouse.
                    @if(!empty($dwError))
                        <br><small>Detail: {{ $dwError }}</small>
                    @endif
                </p>
            </div>
        @else
            {{-- Filter Slicing & Dicing --}}
            <form method="GET" action="{{ route('admin.reports.index') }}" class="card" style="margin-bottom:20px;">
                <div class="dw-filter-bar">
                    <div>
                        <label for="dw-periode">Rentang Waktu</label>
                        <select id="dw-periode" name="periode">
                            <option value="semua" @selected(($dwFilters['periode'] ?? 'semua') === 'semua')>Semua Waktu</option>
                            <option value="tahun_ini" @selected(($dwFilters['periode'] ?? '') === 'tahun_ini')>Tahun Ini ({{ now()->year }})</option>
                            <option value="bulan_ini" @selected(($dwFilters['periode'] ?? '') === 'bulan_ini')>Bulan Ini</option>
                        </select>
                    </div>

                    <div>
                        <label for="dw-tahun">Tahun (dim_waktu)</label>
                        <select id="dw-tahun" name="tahun">
                            <option value="">Semua Tahun</option>
                            @foreach($dwOptions['tahunList'] as $thn)
                                <option value="{{ $thn }}" @selected(($dwFilters['tahun'] ?? null) == $thn)>{{ $thn }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="dw-kuartal">Kuartal</label>
                        <select id="dw-kuartal" name="kuartal">
                            <option value="">Semua Kuartal</option>
                            @for($q = 1; $q <= 4; $q++)
                                <option value="{{ $q }}" @selected(($dwFilters['kuartal'] ?? null) === $q)>Kuartal {{ $q }} (Q{{ $q }})</option>
                            @endfor
                        </select>
                    </div>

                    <div>
                        <label for="dw-bulan">Bulan</label>
                        <select id="dw-bulan" name="bulan">
                            <option value="">Semua Bulan</option>
                            @foreach([
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ] as $num => $namaBln)
                                <option value="{{ $num }}" @selected(($dwFilters['bulan'] ?? null) === $num)>{{ $namaBln }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="dw-kanal">Kanal Penjualan</label>
                        <select id="dw-kanal" name="kanal">
                            <option value="">Semua Kanal</option>
                            <option value="ONLINE" @selected(($dwFilters['kanal'] ?? '') === 'ONLINE')>ONLINE</option>
                            <option value="OFFLINE" @selected(($dwFilters['kanal'] ?? '') === 'OFFLINE')>OFFLINE (POS)</option>
                        </select>
                    </div>

                    <div>
                        <label for="dw-kategori">Kategori Produk</label>
                        <select id="dw-kategori" name="kategori">
                            <option value="">Semua Kategori</option>
                            @foreach($dwOptions['kategoriList'] as $kat)
                                <option value="{{ $kat }}" @selected(($dwFilters['kategori'] ?? '') === $kat)>{{ $kat }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="{{ route('admin.reports.index') }}" class="btn sm light">Reset Filter</a>
                    <button type="submit" class="btn sm">Terapkan Filter OLAP</button>
                </div>
            </form>

            {{-- KPI Cards dari Tabel Fakta DW --}}
            <div class="stats">
                <div class="card stat">
                    Transaksi Penjualan (DW)
                    <strong>{{ number_format($dwSummary['total_transaksi_penjualan'], 0, ',', '.') }}</strong>
                    <small class="muted">{{ number_format($dwSummary['total_baris_penjualan'], 0, ',', '.') }} baris item · {{ number_format($dwSummary['total_unit_terjual'], 0, ',', '.') }} pcs</small>
                </div>

                <div class="card stat">
                    Omzet Bersih (fakta_penjualan)
                    <strong>Rp{{ number_format($dwSummary['total_omzet_bersih'], 0, ',', '.') }}</strong>
                    <small class="muted">Alokasi diskon poin: Rp{{ number_format($dwSummary['total_diskon_poin'], 0, ',', '.') }}</small>
                </div>

                <div class="card stat">
                    Restock Diterima (DW)
                    <strong>{{ number_format($dwSummary['total_po_pembelian'], 0, ',', '.') }} PO</strong>
                    <small class="muted">Total {{ number_format($dwSummary['total_unit_dibeli'], 0, ',', '.') }} pcs masuk gudang</small>
                </div>

                <div class="card stat">
                    Biaya & Pengeluaran (DW)
                    <strong>Rp{{ number_format($dwSummary['total_seluruh_pengeluaran'] ?? $dwSummary['total_biaya_pembelian'], 0, ',', '.') }}</strong>
                    <small class="muted">
                        Restock: Rp{{ number_format($dwSummary['total_biaya_pembelian'], 0, ',', '.') }}
                        · Ops: Rp{{ number_format($dwSummary['total_pengeluaran_ops'] ?? 0, 0, ',', '.') }}
                    </small>
                </div>
            </div>

            <div class="card" style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <span class="muted">Estimasi Arus Kas Bersih Data Warehouse (Omzet Bersih − Seluruh Pengeluaran DW)</span>
                    <h2 style="margin:4px 0 0;">Rp{{ number_format($dwSummary['laba_bersih_dw'] ?? 0, 0, ',', '.') }}</h2>
                </div>
                <span class="badge ok">Skema OLAP: database/dw/umkm_dw_olap_schema.xml</span>
            </div>

            {{-- Grafik 1: Tren Bulanan fakta_penjualan & fakta_pembelian_produk --}}
            <div class="dw-grid-2">
                <div class="card">
                    <h3 style="margin-top:0">Tren Penjualan per Periode (<code>fakta_penjualan</code>)</h3>
                    @if($dwSalesTrend->isEmpty())
                        <p class="muted">Belum ada data pada filter yang dipilih. Klik tombol <b>Sync Data Warehouse (ETL)</b> jika baru ada transaksi.</p>
                    @else
                        @php
                            $maxOmzetTrend = max(1, (float) $dwSalesTrend->max('omzet'));
                            $pts = [];
                            $countPts = $dwSalesTrend->count();
                            foreach ($dwSalesTrend->values() as $idx => $row) {
                                $x = $countPts > 1 ? round(30 + ($idx / ($countPts - 1)) * 440, 1) : 250;
                                $y = round(145 - (($row->omzet / $maxOmzetTrend) * 110), 1);
                                $pts[] = ['x' => $x, 'y' => $y, 'row' => $row];
                            }
                            $polyline = collect($pts)->map(fn ($p) => "{$p['x']},{$p['y']}")->implode(' ');
                        @endphp
                        <svg viewBox="0 0 500 175" style="width:100%;height:auto;background:#faf8f5;border-radius:10px;padding:8px;border:1px solid #eee;">
                            <line x1="30" y1="35" x2="470" y2="35" stroke="#e9e5df" stroke-dasharray="3,3" />
                            <line x1="30" y1="90" x2="470" y2="90" stroke="#e9e5df" stroke-dasharray="3,3" />
                            <line x1="30" y1="145" x2="470" y2="145" stroke="#d9d4cc" />
                            @if(count($pts) > 1)
                                <polyline fill="none" stroke="#238253" stroke-width="3" points="{{ $polyline }}" />
                            @endif
                            @foreach($pts as $p)
                                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5" fill="#238253" />
                                <text x="{{ $p['x'] }}" y="{{ max(16, $p['y'] - 10) }}" text-anchor="middle" font-size="10" fill="#1d1d1b">
                                    {{ number_format($p['row']->omzet / 1000, 0, ',', '.') }}k
                                </text>
                                <text x="{{ $p['x'] }}" y="164" text-anchor="middle" font-size="10" fill="#77736d">
                                    {{ substr($p['row']->nama_bulan, 0, 3) }} {{ $p['row']->tahun }}
                                </text>
                            @endforeach
                        </svg>

                        <div class="dw-bar-list">
                            @foreach($dwSalesTrend as $t)
                                <div class="dw-bar-item">
                                    <div class="dw-bar-head">
                                        <span><b>{{ $t->nama_bulan }} {{ $t->tahun }}</b> ({{ $t->transaksi }} trx · {{ $t->qty }} pcs)</span>
                                        <strong>Rp{{ number_format($t->omzet, 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="dw-bar-track">
                                        <div class="dw-bar-fill green" style="width:{{ round(($t->omzet / $maxOmzetTrend) * 100, 1) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card">
                    <h3 style="margin-top:0">Tren Pembelian Produk (<code>fakta_pembelian_produk</code>)</h3>
                    @if($dwPurchaseTrend->isEmpty())
                        <p class="muted">Belum ada data pembelian produk berstatus DITERIMA pada periode ini.</p>
                    @else
                        @php
                            $maxBiayaTrend = max(1, (float) $dwPurchaseTrend->max('biaya'));
                        @endphp
                        <div class="dw-bar-list">
                            @foreach($dwPurchaseTrend as $pt)
                                <div class="dw-bar-item">
                                    <div class="dw-bar-head">
                                        <span><b>{{ $pt->nama_bulan }} {{ $pt->tahun }}</b> ({{ $pt->po }} PO · {{ $pt->qty }} pcs)</span>
                                        <strong>Rp{{ number_format($pt->biaya, 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="dw-bar-track">
                                        <div class="dw-bar-fill amber" style="width:{{ round(($pt->biaya / $maxBiayaTrend) * 100, 1) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <h3 style="margin-top:24px;">Pembelian per Supplier (<code>dim_supplier</code>)</h3>
                    @if($dwSupplierPurchases->isEmpty())
                        <p class="muted">Belum ada data supplier di tabel fakta pembelian.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <tr>
                                    <th>Supplier</th>
                                    <th>PO</th>
                                    <th>Unit</th>
                                    <th>Total Biaya</th>
                                </tr>
                                @foreach($dwSupplierPurchases as $sp)
                                    <tr>
                                        <td>
                                            <b>{{ $sp->nama_supplier }}</b>
                                            <br><small class="muted">{{ $sp->id_supplier }}</small>
                                        </td>
                                        <td>{{ $sp->po }}</td>
                                        <td>{{ $sp->qty }} pcs</td>
                                        <td>Rp{{ number_format($sp->biaya, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Grafik 2: Distribusi Kategori & Produk pada DW --}}
            <div class="dw-grid-2">
                <div class="card">
                    <h3 style="margin-top:0">Penjualan per Kategori (<code>dim_produk</code>)</h3>
                    @if($dwCategorySales->isEmpty())
                        <p class="muted">Belum ada data kategori pada filter ini.</p>
                    @else
                        @php
                            $maxCatOmzet = max(1, (float) $dwCategorySales->max('omzet'));
                        @endphp
                        <div class="dw-bar-list">
                            @foreach($dwCategorySales as $cat)
                                <div class="dw-bar-item">
                                    <div class="dw-bar-head">
                                        <span><b>{{ $cat->kategori }}</b> ({{ $cat->transaksi }} trx · {{ $cat->qty }} pcs)</span>
                                        <strong>Rp{{ number_format($cat->omzet, 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="dw-bar-track">
                                        <div class="dw-bar-fill" style="width:{{ round(($cat->omzet / $maxCatOmzet) * 100, 1) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card">
                    <h3 style="margin-top:0">Top 10 Produk di Data Warehouse</h3>
                    @if($dwProductSales->isEmpty())
                        <p class="muted">Belum ada data produk terjual di DW.</p>
                    @else
                        @php
                            $maxProdQty = max(1, (int) $dwProductSales->max('qty'));
                        @endphp
                        <div class="dw-bar-list">
                            @foreach($dwProductSales as $prod)
                                <div class="dw-bar-item">
                                    <div class="dw-bar-head">
                                        <span>
                                            <b>{{ $prod->nama_produk }}</b>
                                            <small class="muted">({{ $prod->kategori }})</small>
                                        </span>
                                        <span><b>{{ $prod->qty }} pcs</b> · Rp{{ number_format($prod->omzet, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="dw-bar-track">
                                        <div class="dw-bar-fill green" style="width:{{ round(($prod->qty / $maxProdQty) * 100, 1) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Grafik 3: Pie/Donut Chart Kanal Penjualan & Metode Pembayaran --}}
            <div class="dw-grid-2">
                <div class="card">
                    <h3 style="margin-top:0">Rasio Kanal Penjualan (ONLINE vs OFFLINE)</h3>
                    @if($dwChannelRatio->isEmpty())
                        <p class="muted">Belum ada data kanal penjualan.</p>
                    @else
                        @php
                            $totalChannelOmzet = max(1, (float) $dwChannelRatio->sum('omzet'));
                            $onlineRow = $dwChannelRatio->firstWhere('kanal_penjualan', 'ONLINE');
                            $onlinePct = $onlineRow ? round(($onlineRow->omzet / $totalChannelOmzet) * 100, 1) : 0;
                        @endphp
                        <div class="dw-donut-wrap">
                            <svg width="120" height="120" viewBox="0 0 42 42">
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="#a6741c" stroke-width="6"></circle>
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="#238253" stroke-width="6"
                                    stroke-dasharray="{{ $onlinePct }} {{ 100 - $onlinePct }}" stroke-dashoffset="25"></circle>
                                <text x="21" y="22.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#1d1d1b">
                                    {{ $onlinePct }}%
                                </text>
                            </svg>

                            <div class="dw-legend">
                                @foreach($dwChannelRatio as $ch)
                                    @php
                                        $pct = round(($ch->omzet / $totalChannelOmzet) * 100, 1);
                                        $color = $ch->kanal_penjualan === 'ONLINE' ? '#238253' : '#a6741c';
                                    @endphp
                                    <div class="dw-legend-row">
                                        <span>
                                            <i class="dw-dot" style="background:{{ $color }}"></i>
                                            <b>{{ $ch->kanal_penjualan }}</b> ({{ $pct }}%)
                                            <br><small class="muted">{{ $ch->transaksi }} transaksi · {{ $ch->qty }} pcs</small>
                                        </span>
                                        <strong>Rp{{ number_format($ch->omzet, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <h3 style="margin-top:0">Rasio Metode Pembayaran (QR vs TUNAI)</h3>
                    @if($dwPaymentRatio->isEmpty())
                        <p class="muted">Belum ada data metode pembayaran.</p>
                    @else
                        @php
                            $totalPayOmzet = max(1, (float) $dwPaymentRatio->sum('omzet'));
                            $qrRow = $dwPaymentRatio->firstWhere('metode_pembayaran', 'QR');
                            $qrPct = $qrRow ? round(($qrRow->omzet / $totalPayOmzet) * 100, 1) : 0;
                        @endphp
                        <div class="dw-donut-wrap">
                            <svg width="120" height="120" viewBox="0 0 42 42">
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="#77736d" stroke-width="6"></circle>
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="#1d1d1b" stroke-width="6"
                                    stroke-dasharray="{{ $qrPct }} {{ 100 - $qrPct }}" stroke-dashoffset="25"></circle>
                                <text x="21" y="22.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#1d1d1b">
                                    {{ $qrPct }}%
                                </text>
                            </svg>

                            <div class="dw-legend">
                                @foreach($dwPaymentRatio as $pm)
                                    @php
                                        $pct = round(($pm->omzet / $totalPayOmzet) * 100, 1);
                                        $color = $pm->metode_pembayaran === 'QR' ? '#1d1d1b' : '#77736d';
                                    @endphp
                                    <div class="dw-legend-row">
                                        <span>
                                            <i class="dw-dot" style="background:{{ $color }}"></i>
                                            <b>{{ $pm->metode_pembayaran }}</b> ({{ $pct }}%)
                                            <br><small class="muted">{{ $pm->transaksi }} transaksi · {{ $pm->qty }} pcs</small>
                                        </span>
                                        <strong>Rp{{ number_format($pm->omzet, 0, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tabel Dimensi Pelanggan & Staf pada DW --}}
            <div class="dw-grid-2">
                <div class="card">
                    <h3 style="margin-top:0">Top Pelanggan Member (<code>dim_pelanggan</code>)</h3>
                    @if($dwTopCustomers->isEmpty())
                        <p class="muted">Belum ada transaksi member pada filter ini.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Transaksi</th>
                                    <th>Unit</th>
                                    <th>Total Belanja Bersih</th>
                                </tr>
                                @foreach($dwTopCustomers as $cust)
                                    <tr>
                                        <td>
                                            <b>{{ $cust->nama_pelanggan }}</b>
                                            <br><small class="muted">{{ $cust->id_akun }}</small>
                                        </td>
                                        <td>{{ $cust->transaksi }}</td>
                                        <td>{{ $cust->qty }} pcs</td>
                                        <td>Rp{{ number_format($cust->omzet, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <h3 style="margin-top:0">Kinerja Staf Kasir POS (<code>dim_staf</code>)</h3>
                    @if($dwStaffPerformance->isEmpty())
                        <p class="muted">Belum ada transaksi kasir offline pada filter ini.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <tr>
                                    <th>Nama Staf</th>
                                    <th>Transaksi POS</th>
                                    <th>Unit Terjual</th>
                                    <th>Total Omzet POS</th>
                                </tr>
                                @foreach($dwStaffPerformance as $st)
                                    <tr>
                                        <td>
                                            <b>{{ $st->nama_staf }}</b>
                                            <br><small class="muted">{{ $st->id_akun }} · {{ $st->tipe_akun }}</small>
                                        </td>
                                        <td>{{ $st->transaksi }}</td>
                                        <td>{{ $st->qty }} pcs</td>
                                        <td>Rp{{ number_format($st->omzet, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Grafik & Tabel Fakta 3: Distribusi Pengeluaran (fakta_pengeluaran) --}}
            <div class="card" style="margin-top:20px;">
                <h3 style="margin-top:0">Distribusi Pengeluaran Data Warehouse (<code>fakta_pengeluaran</code>)</h3>
                @if(empty($dwExpenseBreakdown) || $dwExpenseBreakdown->isEmpty())
                    <p class="muted">Belum ada data pada <code>fakta_pengeluaran</code> untuk filter waktu ini.</p>
                @else
                    @php
                        $maxExpNominal = max(1, (float) $dwExpenseBreakdown->max('total_nominal'));
                    @endphp
                    <div class="dw-bar-list">
                        @foreach($dwExpenseBreakdown as $exp)
                            <div class="dw-bar-item">
                                <div class="dw-bar-head">
                                    <span>
                                        <b>{{ $exp->jenis_pengeluaran }}</b>
                                        <span class="badge {{ $exp->kategori_biaya === 'OPERASIONAL' ? 'warn' : 'ok' }}">
                                            {{ $exp->kategori_biaya }}
                                        </span>
                                        <small class="muted">({{ $exp->jumlah_catat }} pencatatan)</small>
                                    </span>
                                    <strong>Rp{{ number_format($exp->total_nominal, 0, ',', '.') }}</strong>
                                </div>
                                <div class="dw-bar-track">
                                    <div class="dw-bar-fill {{ $exp->kategori_biaya === 'OPERASIONAL' ? 'amber' : '' }}"
                                        style="width:{{ round(($exp->total_nominal / $maxExpNominal) * 100, 1) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection

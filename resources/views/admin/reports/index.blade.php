@extends('layouts.admin')
@section('title', 'Laporan Operasional')
@section('content')
    <div class="page-head">
        <div>
            <h1>Monitoring &amp; Laporan Operasional (OLTP)</h1>
            <p class="muted">
                Ringkasan kondisi terkini langsung dari database transaksi operasional.
                Untuk analisis tren historis &amp; multidimensi (Data Warehouse), buka
                <a href="{{ route('admin.analitik') }}"><b>Dashboard Analitik DW</b></a>.
            </p>
        </div>

        <a href="{{ route('admin.analitik') }}" class="btn">
            Buka Analitik DW (OLAP) →
        </a>
    </div>

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
        <span class="muted">Selisih Arus Keuangan (Operasional)</span>
        <h2 style="margin-bottom:0">Rp{{ number_format($cashFlow, 0, ',', '.') }}</h2>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:22px">
        <div class="card">
            <h3 style="margin-top:0">Best Seller</h3>
            @forelse($best as $b)
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;">
                    <span>{{ $b->nama_produk }}</span>
                    <b>{{ $b->qty }} unit</b>
                </div>
            @empty
                <p class="muted">Belum ada data.</p>
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
                <p class="muted">Semua stok varian aman di atas batas minimum.</p>
            @endforelse
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title','Penjualan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Penjualan</h1>
            <p class="muted">
                Monitoring transaksi online Customer dan offline Kasir.
            </p>
        </div>
    </div>

    <form
        method="GET"
        class="card"
        style="display:flex;gap:10px;margin-bottom:15px"
    >
        <select name="kanal">
            <option value="">Semua Kanal</option>
            <option
                value="ONLINE"
                @selected(request('kanal') === 'ONLINE')
            >
                ONLINE
            </option>
            <option
                value="OFFLINE"
                @selected(request('kanal') === 'OFFLINE')
            >
                OFFLINE
            </option>
        </select>

        <select name="status">
            <option value="">Semua Pembayaran</option>
            <option
                value="MENUNGGU"
                @selected(request('status') === 'MENUNGGU')
            >
                MENUNGGU
            </option>
            <option
                value="BERHASIL"
                @selected(request('status') === 'BERHASIL')
            >
                BERHASIL
            </option>
            <option
                value="GAGAL"
                @selected(request('status') === 'GAGAL')
            >
                GAGAL
            </option>
        </select>

        <button class="btn">
            Filter
        </button>
    </form>

    <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Pelaksana</th>
                <th>Member</th>
                <th>Kanal</th>
                <th>Metode Pembayaran</th>
                <th>Status Pembayaran</th>
                <th>Status Penjualan</th>
                <th>Total</th>
                <th></th>
            </tr>

            @foreach($sales as $s)
                <tr>
                    <td>{{ $s->id_penjualan }}</td>

                    <td>{{ $s->tanggal_penjualan }}</td>

                    <td>{{ $s->pelaksana }}</td>

                    <td>
                        {{ $s->member_nama ?: '-' }}
                    </td>

                    <td>
                        {{ $s->kanal_penjualan }}
                    </td>

                    <td>
                        {{ $s->metode_pembayaran }}
                    </td>

                    <td>
                        {{ $s->status_pembayaran }}
                    </td>

                    <td>
                        {{ $s->status_penjualan }}
                    </td>

                    <td>
                        Rp{{ number_format(
                            $s->nominal_bayar,
                            0,
                            ',',
                            '.'
                        ) }}
                    </td>

                    <td>
                        <a
                            class="btn sm"
                            href="{{ route(
                                'admin.sales.show',
                                $s->id_penjualan
                            ) }}"
                        >
                            Detail
                        </a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    @if($sales->hasPages())
        <div
            class="card"
            style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;gap:12px;flex-wrap:wrap;"
        >
            <span class="muted" style="font-size:13px;">
                Menampilkan {{ $sales->firstItem() }}–{{ $sales->lastItem() }}
                dari {{ number_format($sales->total(), 0, ',', '.') }} transaksi
                (Halaman {{ $sales->currentPage() }} / {{ $sales->lastPage() }})
            </span>

            <div style="display:flex;gap:8px;">
                @if($sales->onFirstPage())
                    <span class="btn sm light" style="opacity:.5;cursor:not-allowed;">← Sebelumnya</span>
                @else
                    <a class="btn sm light" href="{{ $sales->previousPageUrl() }}">← Sebelumnya</a>
                @endif

                @if($sales->hasMorePages())
                    <a class="btn sm" href="{{ $sales->nextPageUrl() }}">Berikutnya →</a>
                @else
                    <span class="btn sm light" style="opacity:.5;cursor:not-allowed;">Berikutnya →</span>
                @endif
            </div>
        </div>
    @endif
@endsection
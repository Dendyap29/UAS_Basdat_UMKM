<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPenjualan extends Command
{
    protected $signature = 'dw:etl-penjualan';

    protected $description = 'Salin data penjualan dari database umkm_pakaian ke DW (umkm_dw)';

    public function handle(): int
    {
        $src = DB::connection();      // database lama (umkm_pakaian)
        $dw  = DB::connection('dw');  // database DW (umkm_dw)

        // Transaksi yang dihitung sebagai penjualan: pembayaran berhasil dan tidak batal.
        // Sama dengan acuan ReportController (income) dan PointService.
        $penjualanValid = fn ($q) => $q
            ->where('p.status_pembayaran', 'BERHASIL')
            ->where('p.status_penjualan', '!=', 'BATAL');

        // ---------- 1. Dim_Produk (digabung dengan kategori) ----------
        $this->info('1/6 Mengisi dim_produk...');
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

        // ---------- 2. Dim_Varian_Produk ----------
        $this->info('2/6 Mengisi dim_varian_produk...');
        foreach ($src->table('varian_produk')->get() as $v) {
            $dw->table('dim_varian_produk')->updateOrInsert(
                ['id_varian' => $v->id_varian],
                ['ukuran' => $v->ukuran, 'warna' => $v->warna]
            );
        }

        // ---------- 3. Dim_Staf (ADMIN dan KASIR) dan Dim_Pelanggan (CUSTOMER) ----------
        $this->info('3/6 Mengisi dim_staf dan dim_pelanggan...');
        $akun = $src->table('akun')->get();
        foreach ($akun as $a) {
            if (in_array($a->tipe_akun, ['ADMIN', 'KASIR'], true)) {
                $dw->table('dim_staf')->updateOrInsert(
                    ['id_akun' => $a->id_akun],
                    [
                        'nama_staf' => $a->nama,
                        'tipe_akun' => $a->tipe_akun,
                        'email'     => $a->email,
                    ]
                );
            } elseif ($a->tipe_akun === 'CUSTOMER') {
                $dw->table('dim_pelanggan')->updateOrInsert(
                    ['id_akun' => $a->id_akun],
                    [
                        'nama_pelanggan' => $a->nama,
                        'tanggal_daftar' => $a->created_at
                            ? Carbon::parse($a->created_at)->toDateString()
                            : null,
                    ]
                );
            }
        }

        // ---------- 4. Dim_Waktu (dari tanggal penjualan yang valid) ----------
        $this->info('4/6 Mengisi dim_waktu...');
        $tanggal = $src->table('penjualan as p')
            ->tap($penjualanValid)
            ->selectRaw('DATE(p.tanggal_penjualan) as tgl')
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

        // ---------- 5. Kamus kunci: ID lama -> kunci baru (sk_...) ----------
        $this->info('5/6 Menyiapkan kamus kunci...');
        $mapProduk     = $dw->table('dim_produk')->pluck('sk_produk', 'id_produk')->all();
        $mapVarian     = $dw->table('dim_varian_produk')->pluck('sk_varian', 'id_varian')->all();
        $mapStaf       = $dw->table('dim_staf')->pluck('sk_staf', 'id_akun')->all();
        $mapPelanggan  = $dw->table('dim_pelanggan')->pluck('sk_pelanggan', 'id_akun')->all();

        // ---------- 6. Fakta ----------
        $this->info('6/6 Mengisi fakta_penjualan...');

        $penjualan = $src->table('detail_penjualan as d')
            ->join('penjualan as p', 'd.id_penjualan', '=', 'p.id_penjualan')
            ->join('varian_produk as v', 'd.id_varian', '=', 'v.id_varian')
            ->leftJoin('penjualan_member as pm', 'p.id_penjualan', '=', 'pm.id_penjualan')
            ->tap($penjualanValid)
            ->orderBy('d.id_penjualan')
            ->orderBy('d.id_detail_penjualan')
            ->select(
                'd.id_detail_penjualan',
                'd.id_penjualan',
                'd.id_varian',
                'd.jumlah',
                'd.harga_satuan',
                'p.id_akun',
                'p.tanggal_penjualan',
                'p.kanal_penjualan',
                'p.metode_pembayaran',
                'p.nominal_bayar',
                'p.diskon_poin',
                'pm.id_akun_customer',
                'v.id_produk'
            )
            ->get()
            ->groupBy('id_penjualan');

        if ($penjualan->isEmpty()) {
            $this->warn('Belum ada penjualan berstatus BERHASIL, jadi tabel fakta masih kosong.');
        }

        $transaksiMasuk = 0;
        $barisMasuk     = 0;
        $dilewati       = 0;
        $takSinkron     = [];

        foreach ($penjualan as $idPenjualan => $baris) {
            $h = $baris->first(); // data header transaksi (sama di semua baris)

            // Siapa pelanggan dan siapa staf bergantung pada kanal:
            //   ONLINE  -> penjualan.id_akun adalah customer, tidak ada staf
            //   OFFLINE -> penjualan.id_akun adalah kasir, customer (jika member) ada di penjualan_member
            if ($h->kanal_penjualan === 'ONLINE') {
                $idPelanggan = $h->id_akun;
                $idStaf      = null;
            } else {
                $idPelanggan = $h->id_akun_customer; // bisa null (bukan member)
                $idStaf      = $h->id_akun;
            }

            $skPelanggan = $idPelanggan ? ($mapPelanggan[$idPelanggan] ?? null) : null;
            $skStaf      = $idStaf ? ($mapStaf[$idStaf] ?? null) : null;

            // Dimensi wajib ada, kalau tidak lewati seluruh transaksi
            // supaya alokasi diskon poin tidak timpang.
            $lengkap = ($idPelanggan === null || $skPelanggan !== null)
                && ($idStaf === null || $skStaf !== null)
                && $baris->every(
                    fn ($r) => isset($mapProduk[$r->id_produk], $mapVarian[$r->id_varian])
                );

            if (!$lengkap) {
                $this->warn("Dilewati {$idPenjualan}: data dimensinya tidak lengkap.");
                $dilewati++;
                continue;
            }

            // Diskon poin berlaku untuk seluruh transaksi, jadi dibagi proporsional ke tiap
            // baris. Baris terakhir menampung sisa pembulatan agar totalnya persis sama.
            $subtotalTransaksi = $baris->sum(fn ($r) => $r->jumlah * $r->harga_satuan);
            $diskonPoin        = (float) $h->diskon_poin;
            $sisaDiskon        = $diskonPoin;
            $jumlahBaris       = $baris->count();
            $urutan            = 0;
            $totalBersih       = 0.0;

            $dw->transaction(function () use (
                $dw, $baris, $h, $idPenjualan, $skPelanggan, $skStaf,
                $subtotalTransaksi, $diskonPoin, &$sisaDiskon, $jumlahBaris,
                &$urutan, &$totalBersih, &$barisMasuk, $mapProduk, $mapVarian
            ) {
                foreach ($baris as $r) {
                    $urutan++;
                    $subtotal = round($r->jumlah * $r->harga_satuan, 2);

                    if ($diskonPoin <= 0 || $subtotalTransaksi <= 0) {
                        $alokasi = 0.0;
                    } elseif ($urutan === $jumlahBaris) {
                        $alokasi = round($sisaDiskon, 2);
                    } else {
                        $alokasi = round($diskonPoin * $subtotal / $subtotalTransaksi, 2);
                        $sisaDiskon -= $alokasi;
                    }

                    $bersih = round($subtotal - $alokasi, 2);
                    $totalBersih += $bersih;

                    $dw->table('fakta_penjualan')->updateOrInsert(
                        ['id_detail_penjualan' => $r->id_detail_penjualan],
                        [
                            'no_penjualan'        => $idPenjualan,
                            'id_waktu'            => (int) Carbon::parse($h->tanggal_penjualan)->format('Ymd'),
                            'sk_produk'           => $mapProduk[$r->id_produk],
                            'sk_varian'           => $mapVarian[$r->id_varian],
                            'sk_pelanggan'        => $skPelanggan,
                            'sk_staf'             => $skStaf,
                            'kanal_penjualan'     => $h->kanal_penjualan,
                            'metode_pembayaran'   => $h->metode_pembayaran,
                            'jumlah_terjual'      => $r->jumlah,
                            'harga_satuan'        => $r->harga_satuan,
                            'subtotal_item'       => $subtotal,
                            'diskon_poin_alokasi' => $alokasi,
                            'total_bersih_item'   => $bersih,
                        ]
                    );
                    $barisMasuk++;
                }
            });

            $transaksiMasuk++;

            // Pemeriksaan: total bersih di DW harus sama dengan nominal_bayar di sumber
            if (abs($totalBersih - (float) $h->nominal_bayar) > 0.01) {
                $takSinkron[] = sprintf(
                    '%s (DW %s, sumber %s)',
                    $idPenjualan,
                    number_format($totalBersih, 2, '.', ''),
                    number_format((float) $h->nominal_bayar, 2, '.', '')
                );
            }
        }

        if ($takSinkron) {
            $this->warn(count($takSinkron) . ' transaksi total bersihnya berbeda dari nominal_bayar di sumber:');
            foreach (array_slice($takSinkron, 0, 10) as $baris) {
                $this->line("  - {$baris}");
            }
            if (count($takSinkron) > 10) {
                $this->line('  ... dan ' . (count($takSinkron) - 10) . ' lainnya.');
            }
        }

        $this->info("Selesai. {$transaksiMasuk} transaksi ({$barisMasuk} baris) masuk ke tabel fakta, {$dilewati} dilewati.");

        return self::SUCCESS;
    }
}

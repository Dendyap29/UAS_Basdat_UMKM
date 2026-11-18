<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPengeluaran extends Command
{
    protected $signature = 'dw:etl-pengeluaran';

    protected $description = 'Salin data pengeluaran dari database umkm_pakaian ke DW (umkm_dw)';

    public function handle(): int
    {
        $src = DB::connection();
        $dw  = DB::connection('dw');

        // ---------- 1. Dim_Staf (ADMIN dan KASIR) ----------
        $this->info('1/3 Mengisi dim_staf...');
        $staf = $src->table('akun')->whereIn('tipe_akun', ['ADMIN', 'KASIR'])->get();
        foreach ($staf as $a) {
            $dw->table('dim_staf')->updateOrInsert(
                ['id_akun' => $a->id_akun],
                [
                    'nama_staf' => $a->nama,
                    'tipe_akun' => $a->tipe_akun,
                    'email'     => $a->email,
                ]
            );
        }

        // ---------- 2. Dim_Waktu (dari tanggal pengeluaran) ----------
        $this->info('2/3 Mengisi dim_waktu...');
        $tanggal = $src->table('pengeluaran')
            ->selectRaw('DATE(tanggal_pengeluaran) as tgl')
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

        // ---------- 3. Fakta Pengeluaran ----------
        $this->info('3/3 Mengisi fakta_pengeluaran...');
        $mapStaf = $dw->table('dim_staf')->pluck('sk_staf', 'id_akun')->all();

        $rows = $src->table('pengeluaran')
            ->orderBy('id_pengeluaran')
            ->get();

        $dimuat = 0;
        foreach ($rows as $r) {
            $skStaf = $mapStaf[$r->id_akun] ?? null;
            if (!$skStaf) {
                $this->warn("Dilewati {$r->id_pengeluaran}: staf {$r->id_akun} tidak ditemukan di dim_staf.");
                continue;
            }

            $kategoriBiaya = $r->jenis_pengeluaran === 'PEMBELIAN_PRODUK'
                ? 'RESTOCK_PRODUK'
                : 'OPERASIONAL';

            $dw->table('fakta_pengeluaran')->updateOrInsert(
                ['id_pengeluaran' => $r->id_pengeluaran],
                [
                    'id_pembelian'        => $r->id_pembelian,
                    'id_waktu'            => (int) Carbon::parse($r->tanggal_pengeluaran)->format('Ymd'),
                    'sk_staf'             => $skStaf,
                    'kategori_biaya'      => $kategoriBiaya,
                    'jenis_pengeluaran'   => $r->jenis_pengeluaran,
                    'nominal_pengeluaran' => round((float) $r->nominal, 2),
                ]
            );
            $dimuat++;
        }

        $this->info("Selesai. {$dimuat} baris masuk ke fakta_pengeluaran.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrunesStaleFacts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EtlPengeluaran extends Command
{
    use PrunesStaleFacts;

    protected $signature = 'dw:etl-pengeluaran {--skip-dims : Lewati pembaruan tabel dimensi}';

    protected $description = 'Salin data pengeluaran dari database OLTP ke fact_pengeluaran di umkm_dw';

    public function handle(): int
    {
        if (!$this->option('skip-dims')) {
            $this->call('dw:etl-dimensi');
        }

        $src = DB::connection();
        $dw  = DB::connection('dw');

        $this->info('Mengekstrak & mentransformasi fact_pengeluaran...');
        $mapJenis = $dw->table('dim_jenis_pengeluaran')->pluck('jenis_pengeluaran_key', 'jenis_pengeluaran')->all();
        $mapStaf  = $dw->table('dim_staf')->pluck('staf_key', 'id_akun')->all();

        $rows = $src->table('pengeluaran')
            ->orderBy('id_pengeluaran')
            ->get();

        $factRows = [];
        $dilewati = 0;

        foreach ($rows as $r) {
            $jenisKey = $mapJenis[$r->jenis_pengeluaran] ?? null;
            $stafKey  = $mapStaf[$r->id_akun] ?? null;

            if ($jenisKey === null || $stafKey === null) {
                $dilewati++;
                continue;
            }

            $factRows[] = [
                'waktu_key'             => (int) Carbon::parse($r->tanggal_pengeluaran)->format('Ymd'),
                'jenis_pengeluaran_key' => $jenisKey,
                'staf_key'              => $stafKey,
                'id_pengeluaran'        => $r->id_pengeluaran,
                'nominal'               => round((float) $r->nominal, 2),
            ];
        }

        $dw->transaction(function () use ($dw, $factRows) {
            foreach (array_chunk($factRows, 100) as $chunk) {
                $dw->table('fact_pengeluaran')->upsert(
                    $chunk,
                    ['id_pengeluaran'],
                    ['waktu_key', 'jenis_pengeluaran_key', 'staf_key', 'nominal']
                );
            }
        });

        $dihapus = $this->pruneStale($dw, 'fact_pengeluaran', 'id_pengeluaran', array_column($factRows, 'id_pengeluaran'));

        $dimuat = count($factRows);
        $this->info("Selesai. {$dimuat} baris masuk ke fact_pengeluaran, {$dilewati} dilewati, {$dihapus} baris basi dihapus.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Database\ConnectionInterface;

/**
 * Menghapus baris fakta di DW yang sudah tidak valid di OLTP.
 *
 * ETL memakai upsert (idempoten), tetapi upsert saja tidak membuang baris yang
 * sebelumnya valid lalu berubah, misalnya transaksi yang dibatalkan setelah
 * dimuat. Setelah upsert, id sumber yang tidak lagi lolos filter dihapus agar
 * DW selalu sama dengan OLTP.
 */
trait PrunesStaleFacts
{
    /**
     * @param  array<int, string>  $validIds  id sumber yang baru saja dimuat
     * @return int jumlah baris fakta yang dihapus
     */
    protected function pruneStale(ConnectionInterface $dw, string $table, string $column, array $validIds): int
    {
        $valid = array_flip($validIds);
        $stale = [];

        foreach ($dw->table($table)->pluck($column) as $id) {
            if (!isset($valid[$id])) {
                $stale[] = $id;
            }
        }

        foreach (array_chunk($stale, 500) as $chunk) {
            $dw->table($table)->whereIn($column, $chunk)->delete();
        }

        return count($stale);
    }
}

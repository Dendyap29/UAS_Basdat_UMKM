<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dw:sync', function () {
    $this->call('dw:etl-dimensi');
    $this->call('dw:etl-penjualan', ['--skip-dims' => true]);
    $this->call('dw:etl-pembelian', ['--skip-dims' => true]);
    $this->call('dw:etl-pengeluaran', ['--skip-dims' => true]);
})->purpose('Jalankan seluruh pipeline ETL (dimensi -> fakta) ke Data Warehouse umkm_dw');

Schedule::command('dw:sync')->hourly();

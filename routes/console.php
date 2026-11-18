<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dw:sync', function () {
    $this->call('dw:etl-pembelian');
    $this->call('dw:etl-penjualan');
    $this->call('dw:etl-pengeluaran');
})->purpose('Jalankan seluruh proses ETL (pembelian, penjualan, dan pengeluaran) ke Data Warehouse');

Schedule::command('dw:etl-pembelian')->hourly();
Schedule::command('dw:etl-penjualan')->hourly();
Schedule::command('dw:etl-pengeluaran')->hourly();


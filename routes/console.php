<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ETL Data Warehouse tidak dijalankan dari Laravel. Satu-satunya jalur ETL adalah
// job Pentaho: database/dw/pentaho/job_etl_umkm_dw.kjb (lihat docs/ETL_DOCUMENTATION.md).

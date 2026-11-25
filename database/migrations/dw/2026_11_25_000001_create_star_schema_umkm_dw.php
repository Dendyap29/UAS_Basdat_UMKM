<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema bintang Data Warehouse FORMA (umkm_dw): 6 dimensi + 3 fakta + 1 tabel log validasi.
 *
 * Migrasi ini HANYA membuat struktur tabel. Seluruh isi tabel (termasuk baris kunci 0)
 * dimuat oleh job Pentaho database/dw/pentaho/job_etl_umkm_dw.kjb.
 *
 * Jalankan: php artisan migrate --database=dw --path=database/migrations/dw
 */
return new class extends Migration
{
    public function up(): void
    {
        $dw = Schema::connection('dw');

        // ---------- Dimensi ----------

        // Conformed: dipakai ketiga fakta. Kunci pintar YYYYMMDD, dibangkitkan ETL.
        $dw->create('dim_waktu', function (Blueprint $t) {
            $t->unsignedInteger('waktu_key')->primary();
            $t->date('tanggal')->unique();
            $t->unsignedTinyInteger('hari_dalam_minggu'); // 1 = Senin ... 7 = Minggu
            $t->string('nama_hari', 10);
            $t->string('jenis_hari', 12);                  // Hari kerja / Akhir pekan
            $t->unsignedTinyInteger('bulan');
            $t->string('nama_bulan', 12);
            $t->unsignedTinyInteger('kuartal');
            $t->unsignedSmallInteger('tahun');
        });

        // Conformed: dipakai fakta penjualan dan pembelian. Hierarki Kategori > Produk > Varian.
        $dw->create('dim_produk', function (Blueprint $t) {
            $t->increments('produk_key');
            $t->string('id_varian')->unique();
            $t->string('ukuran', 10);
            $t->string('warna', 50);
            $t->string('id_produk');
            $t->string('nama_produk');
            $t->string('nama_kategori');
        });

        // Baris 0 = GUEST (pembeli toko yang bukan member).
        $dw->create('dim_pelanggan', function (Blueprint $t) {
            $t->increments('pelanggan_key');
            $t->string('id_akun')->unique();
            $t->string('nama');
            $t->string('tipe_pelanggan', 10);              // MEMBER / GUEST
            $t->date('tanggal_daftar')->nullable();
        });

        // Baris 0 = Tanpa promo.
        $dw->create('dim_promo', function (Blueprint $t) {
            $t->increments('promo_key');
            $t->string('id_promo')->unique();
            $t->string('nama_promo');
            $t->decimal('persen_diskon', 5, 2);
            $t->string('kelompok_diskon', 15);
            $t->date('tanggal_mulai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->unsignedSmallInteger('durasi_hari');
        });

        // Junk dimension: kombinasi kanal x metode bayar yang benar-benar terjadi.
        $dw->create('dim_transaksi', function (Blueprint $t) {
            $t->increments('transaksi_key');
            $t->string('kanal_penjualan', 10);
            $t->string('metode_pembayaran', 10);
            $t->unique(['kanal_penjualan', 'metode_pembayaran']);
        });

        $dw->create('dim_supplier', function (Blueprint $t) {
            $t->increments('supplier_key');
            $t->string('id_supplier')->unique();
            $t->string('nama_supplier');
            $t->string('kota', 50);
        });

        // ---------- Fakta ----------

        // Grain: 1 varian dalam 1 nota penjualan yang BERHASIL dan tidak BATAL.
        $dw->create('fact_penjualan', function (Blueprint $t) {
            $t->bigIncrements('penjualan_fact_id');
            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('produk_key');
            $t->unsignedInteger('pelanggan_key');
            $t->unsignedInteger('promo_key');
            $t->unsignedInteger('transaksi_key');
            $t->string('id_penjualan')->index();           // degenerate dimension
            $t->string('id_detail_penjualan')->unique();   // kunci sumber, cegah duplikat
            $t->integer('jumlah');
            $t->decimal('nilai_kotor', 14, 2);             // harga normal x jumlah
            $t->decimal('diskon_promo', 14, 2);
            $t->decimal('diskon_poin', 14, 2);             // alokasi proporsional dari nota
            $t->decimal('pendapatan_bersih', 14, 2);       // nilai_kotor - diskon_promo - diskon_poin
            $t->decimal('hpp', 14, 2);
            $t->decimal('laba_kotor', 14, 2);              // pendapatan_bersih - hpp
            $t->decimal('poin_didapat', 12, 2);            // alokasi proporsional dari nota
            $t->decimal('poin_ditukar', 12, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('produk_key')->references('produk_key')->on('dim_produk');
            $t->foreign('pelanggan_key')->references('pelanggan_key')->on('dim_pelanggan');
            $t->foreign('promo_key')->references('promo_key')->on('dim_promo');
            $t->foreign('transaksi_key')->references('transaksi_key')->on('dim_transaksi');
        });

        // Grain: 1 varian dalam 1 pembelian berstatus DITERIMA (tanggal = tanggal diterima).
        $dw->create('fact_pembelian', function (Blueprint $t) {
            $t->bigIncrements('pembelian_fact_id');
            $t->unsignedInteger('waktu_key');
            $t->unsignedInteger('produk_key');
            $t->unsignedInteger('supplier_key');
            $t->string('id_pembelian')->index();           // degenerate dimension
            $t->string('id_detail_pembelian')->unique();
            $t->integer('jumlah');
            $t->decimal('total_biaya', 14, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
            $t->foreign('produk_key')->references('produk_key')->on('dim_produk');
            $t->foreign('supplier_key')->references('supplier_key')->on('dim_supplier');
        });

        // Grain: 1 pengeluaran OPERASIONAL. Pembelian stok (PEMBELIAN_PRODUK) tidak dimuat
        // karena sudah tercatat di fact_pembelian.
        $dw->create('fact_pengeluaran', function (Blueprint $t) {
            $t->bigIncrements('pengeluaran_fact_id');
            $t->unsignedInteger('waktu_key');
            $t->string('id_pengeluaran')->unique();        // degenerate dimension
            $t->string('jenis_pengeluaran', 20);           // degenerate dimension
            $t->decimal('nominal', 14, 2);

            $t->foreign('waktu_key')->references('waktu_key')->on('dim_waktu');
        });

        // ---------- Metadata ETL ----------

        // Hasil pemeriksaan rekonsiliasi OLTP vs DW pada setiap eksekusi job (bukan fakta/dimensi).
        $dw->create('etl_validasi', function (Blueprint $t) {
            $t->increments('id');
            $t->dateTime('dijalankan_pada');
            $t->string('kode', 5);
            $t->string('pemeriksaan');
            $t->decimal('nilai_oltp', 18, 2);
            $t->decimal('nilai_dw', 18, 2);
            $t->string('status', 4);                       // PASS / FAIL
        });
    }

    public function down(): void
    {
        $dw = Schema::connection('dw');

        foreach ([
            'etl_validasi', 'fact_pengeluaran', 'fact_pembelian', 'fact_penjualan',
            'dim_supplier', 'dim_transaksi', 'dim_promo', 'dim_pelanggan', 'dim_produk', 'dim_waktu',
        ] as $table) {
            $dw->dropIfExists($table);
        }
    }
};

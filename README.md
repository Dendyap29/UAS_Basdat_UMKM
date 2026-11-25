# FORMA Fashion — Sistem Monitoring Transaksi Keuangan & Persediaan UMKM Pakaian + Data Warehouse (OLAP)

**FORMA Fashion** adalah aplikasi web untuk pengelolaan operasional dan analitik **UMKM Pakaian** yang mengintegrasikan dua arsitektur database:
1. **Database Operasional (OLTP — `umkm_pakaian`)**:
   * **E-Commerce Online** untuk pelanggan (`CUSTOMER`)
   * **Point of Sale (POS) Kasir** untuk transaksi toko offline (`KASIR`)
   * **Backoffice Operasional & Kartu Stok** untuk pengelola (`ADMIN`)
2. **Database Analitik (Data Warehouse — `umkm_dw`)**:
   * **Galaxy schema**: 3 tabel fakta (`fact_penjualan`, `fact_pembelian`, `fact_pengeluaran`) dan 6 tabel dimensi (`dim_waktu`, `dim_produk`, `dim_pelanggan`, `dim_promo`, `dim_transaksi`, `dim_supplier`)
   * **ETL Pentaho** satu jalur: `database/dw/pentaho/job_etl_umkm_dw.kjb` (reset, 6 dimensi, 3 fakta, 20 pemeriksaan validasi)
   * **OLAP Mondrian**: [umkm_dw_olap_schema.xml](database/dw/umkm_dw_olap_schema.xml) (3 cube + virtual cube `Keuangan`), query MDX teruji di [olap_mdx_queries.mdx](database/dw/mondrian/olap_mdx_queries.mdx)
   * **Dashboard analitik** (`/admin/analitik`) yang hanya membaca DW, terpisah dari laporan operasional OLTP (`/admin/laporan`)

> **Untuk UAS, mulai dari [docs/FINAL_DESIGN_DECISION.md](docs/FINAL_DESIGN_DECISION.md)** (alasan 3 fakta dan 6 dimensi), lalu [docs/SETUP_AND_RUN.md](docs/SETUP_AND_RUN.md) untuk menjalankan semuanya dan [docs/presentation/](docs/presentation/) untuk alur demo.

---

## Daftar Isi
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Hak Akses & Fitur Berdasarkan Peran (RBAC)](#hak-akses--fitur-berdasarkan-peran-rbac)
- [Aturan & Logika Bisnis Inti](#aturan--logika-bisnis-inti)
- [Arsitektur Basis Data](#arsitektur-basis-data)
  - [1. Skema Operasional (OLTP — `umkm_pakaian`)](#1-skema-operasional-oltp--umkm_pakaian)
  - [2. Skema Data Warehouse (Star Schema — `umkm_dw`)](#2-skema-data-warehouse-star-schema--umkm_dw)
- [Proses ETL & OLAP](#proses-etl--olap)
- [Panduan Instalasi & Konfigurasi](#panduan-instalasi--konfigurasi)
- [Akun Demo Bawaan (`PrototypeSeeder`)](#akun-demo-bawaan-prototypeseeder)
- [Pengujian Sistem (`PHPUnit`)](#pengujian-sistem-phpunit)
- [Struktur Direktori Utama](#struktur-direktori-utama)

---

## Teknologi yang Digunakan
* **Backend Framework**: Laravel 12 (`php: ^8.2`)
* **Database Operasional (OLTP)**: MySQL (`umkm_pakaian`)
* **Database Analitik (Data Warehouse)**: MySQL (`umkm_dw`)
* **Frontend**: Laravel Blade + CSS Kustom (`public/css/forma.css`) + **Chart.js 4**
* **ETL Engine**: Pentaho Data Integration 9.4 (Spoon/Kitchen): 10 transformation (`.ktr`) + 1 job (`.kjb`) di `database/dw/pentaho/`
* **OLAP**: Mondrian (Schema Workbench) dengan skema `database/dw/umkm_dw_olap_schema.xml` dan query MDX `database/dw/mondrian/olap_mdx_queries.mdx`; padanan SQL di `database/dw/olap_queries.sql`
* **Lingkungan lokal yang diuji**: Laragon (MySQL 8.0.30), PHP 8.2, Java 21
* **Testing**: PHPUnit 11 (`FinalSystemSmokeTest`, `DataWarehouseDashboardTest`)

---

## Hak Akses & Fitur Berdasarkan Peran (RBAC)

Sistem menerapkan satu pintu autentikasi (`/login`) yang menerima **ID Akun**, **Username**, atau **Email**, lalu mengarahkan pengguna sesuai `tipe_akun` melalui `RoleMiddleware`:

### 1. Publik / Pengunjung (`/`)
* **Landing Page (`home.blade.php`)**: Menampilkan 4 produk aktif terbaru yang masih memiliki stok varian `> 0`.
* **Login & Registrasi (`/login`, `/register`)**: Pendaftaran mandiri untuk akun `CUSTOMER` baru.

### 2. Customer (`CUSTOMER` — Prefix `/customer`)
* **Dashboard Pelanggan**: Saldo poin member, top best seller, dan ringkasan pesanan.
* **Katalog, Promo & Detail Produk (`/customer/katalog`, `/customer/promo`, `/customer/produk/{id}`)**: Pencarian, filter kategori, informasi harga diskon promo aktif, serta pemilihan varian ukuran dan warna.
* **Keranjang Belanja (`/customer/keranjang`)**: Pengelolaan keranjang berbasis session dengan validasi stok varian.
* **Checkout & Simulasi Pembayaran QR (`/customer/checkout`, `/customer/pembayaran/{id}`)**: Alamat pengiriman, redeem poin, serta simulasi bayar `BERHASIL` / `GAGAL`.
* **Manajemen Pesanan & Profil (`/customer/pesanan`, `/customer/profil`)**: Pelacakan status (`BARU` → `DIPROSES` → `DIKIRIM` → `SELESAI` atau `BATAL`), pembatalan pesanan `MENUNGGU`, dan pembaruan profil.

### 3. Kasir (`KASIR` — Prefix `/kasir`)
* **Dashboard & Riwayat Kasir (`/kasir`, `/kasir/riwayat`)**: Pantauan transaksi kasir.
* **Point of Sale / POS Offline (`/kasir/pos`)**: Pemilihan produk & varian, penautan member (`CUSTOMER`) atau pembeli *guest* non-member, redeem/earn poin, pilihan pembayaran `TUNAI` atau `QR`, dan cetak struk (`/kasir/struk/{id}`).

### 4. Admin (`ADMIN` — Prefix `/admin`)
* **Dashboard Admin (`/admin`)**: Ringkasan penjualan, transaksi, produk, pelanggan, stok menipis, dan transaksi terbaru.
* **Master Data**: Akun Staf (`/admin/akun`), Kategori (`/admin/kategori`), Produk & Varian (`/admin/produk`), serta Supplier (`/admin/supplier`).
* **Pembelian / Restock (`/admin/pembelian`)**: Pembuatan PO (`DIPESAN`), penerimaan barang (`DITERIMA`, mengisi `tanggal_diterima`, menambah stok varian, mencatat riwayat `mutasi_stok` `MASUK`, serta membuat `pengeluaran` `PEMBELIAN_PRODUK` otomatis), dan pembatalan (`BATAL`).
* **Monitoring Penjualan (`/admin/penjualan`)**: Filter kanal & status bayar serta pembaruan status pesanan online berbayar (`DIPROSES` → `DIKIRIM` → `SELESAI`).
* **Promo & Pengeluaran (`/admin/promo`, `/admin/pengeluaran`)**: Kampanye promo diskon persentase per periode dan pencatatan pengeluaran operasional manual (`LISTRIK`, `INTERNET`, `SEWA`, `ONGKIR`, `PERAWATAN`, `LAINNYA`).
* **Laporan Operasional OLTP (`/admin/laporan` — `ReportController`)**: Ringkasan keuangan real-time, produk *best seller*, dan daftar stok minimum langsung dari `umkm_pakaian`.
* **Dashboard Analitik Data Warehouse (`/admin/analitik` — `AnalyticsController`)**:
  * Membaca **hanya dari koneksi `dw` (`umkm_dw`)**.
  * Dilengkapi filter **Slice & Dice** (`Tahun`, `Kuartal`, `Kategori`, `Kanal`) serta tombol **`↻ Refresh Data Warehouse (ETL)`**.
  * Grafik **Chart.js**: Tren Omzet & Laba Kotor bulanan (Q1), Arus Kas Bulanan / Drill-across (Q11), Omzet per Kategori (Q2), Top 10 Produk Terlaris (Q2), Hari Teramai Senin–Minggu (Q8), Kanal & Metode Bayar (Q4), Member vs Guest (Q6), Komposisi Pengeluaran per Jenis (Q10), dan Pembelian per Supplier (Q9), ditambah tabel Pivot Ukuran × Warna (Q3), Efektivitas Promo (Q5), Top Customer (Q6), dan Kinerja Kasir (Q7).
  * Cakupan filter: Tahun & Kuartal untuk semua grafik, Kategori untuk penjualan & pembelian, Kanal hanya penjualan. Arus kas hanya tampil bila filter Kategori/Kanal kosong.

---

## Aturan & Logika Bisnis Inti

1. **`PricingService` (`app/Services/PricingService.php`)**:
   * Menentukan promo aktif dengan diskon persentase terbesar untuk setiap produk (`activePromo()`).
   * Menyimpan *snapshot* `harga_normal`, `harga_satuan` (setelah promo), `persen_diskon`, dan `id_promo` pada `detail_penjualan`.
2. **`PointService` (`app/Services/PointService.php`)**:
   * `1 Poin = Rp500` (`POINT_VALUE = 500`).
   * Perolehan `1 Poin` tiap kelipatan `Rp10.000` (`EARN_PER_RUPIAH = 10000`) dari subtotal setelah diskon promo (sebelum redeem poin) pada transaksi `BERHASIL` & bukan `BATAL` (online maupun offline via `penjualan_member`).
3. **`SaleService` (`app/Services/SaleService.php`)**:
   * Menggunakan `DB::transaction` dan `lockForUpdate()` saat memvalidasi stok & poin, mengurangi `varian_produk.stok`, serta mencatat histori keluar barang pada `mutasi_stok` (`KELUAR`).

---

## Arsitektur Basis Data

### 1. Skema Operasional (OLTP — `umkm_pakaian`)
Lokasi migrasi: `database/migrations/` (14 tabel: 13 tabel transaksi utama + tabel kartu stok `mutasi_stok`).

* `akun`, `kategori_produk`, `produk`, `varian_produk`, `supplier`
* `pembelian_produk` (dilengkapi `tanggal_diterima`), `detail_pembelian`
* `penjualan`, `penjualan_member`, `detail_penjualan` (dilengkapi `harga_normal`, `persen_diskon`, `id_promo`)
* `pengeluaran`, `promo`, `promo_produk`, `mutasi_stok` (`MASUK`, `KELUAR`, `PENYESUAIAN`)

### 2. Skema Data Warehouse (`umkm_dw`)
Struktur dibuat oleh migrasi `database/migrations/dw/` (DDL setara: `database/dw/umkm_dw_schema.sql`); isinya dimuat oleh Pentaho.

| Fakta | Grain | Dimensi | Measure |
| :--- | :--- | :--- | :--- |
| `fact_penjualan` | 1 varian dalam 1 nota valid (BERHASIL, tidak BATAL) | waktu, produk, pelanggan, promo, transaksi | jumlah, nilai_kotor, diskon_promo, diskon_poin, pendapatan_bersih, hpp, laba_kotor, poin_didapat, poin_ditukar |
| `fact_pembelian` | 1 varian dalam 1 pembelian DITERIMA | waktu, produk, supplier | jumlah, total_biaya |
| `fact_pengeluaran` | 1 pengeluaran operasional (tanpa PEMBELIAN_PRODUK) | waktu (+ jenis sebagai degenerate dimension) | nominal |

Detail dan alasan desain: [docs/DATA_WAREHOUSE_DESIGN.md](docs/DATA_WAREHOUSE_DESIGN.md), [docs/STAR_SCHEMA.md](docs/STAR_SCHEMA.md).

---

## Proses ETL & OLAP

* **ETL**: jalankan `database/dw/pentaho/job_etl_umkm_dw.kjb` di Spoon, atau headless lewat Kitchen (lihat [docs/ETL_DOCUMENTATION.md](docs/ETL_DOCUMENTATION.md)). Job gagal otomatis jika ada pemeriksaan rekonsiliasi yang FAIL. Laravel tidak menjalankan ETL.
* **OLAP**: buka `database/dw/umkm_dw_olap_schema.xml` di Schema Workbench, atau jalankan seluruh MDX sekaligus: `powershell -ExecutionPolicy Bypass -File database\dw\mondrian
un_mdx.ps1` (lihat [docs/OLAP_MDX_DOCUMENTATION.md](docs/OLAP_MDX_DOCUMENTATION.md)).
* **Validasi**: `mysql -uroot umkm_dw < database/dw/validasi_etl.sql` (20 pemeriksaan OLTP vs DW).

---

## Panduan Instalasi & Konfigurasi

### 1. Buat Database & Atur `.env`
Buat dua database di MySQL/phpMyAdmin: `umkm_pakaian` (OLTP) dan `umkm_dw` (DW).
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=umkm_pakaian
DB_USERNAME=root
DB_PASSWORD=

DB_DW_HOST=127.0.0.1
DB_DW_PORT=3306
DB_DW_DATABASE=umkm_dw
DB_DW_USERNAME=root
DB_DW_PASSWORD=
```

### 2. Migrasi, Seeder 12 Bulan, & ETL
```bash
composer install
php artisan key:generate

# Migrasi & seed database OLTP (32 produk, 6 kategori, 160 customer, 10 promo, transaksi 1 Des 2025 - 30 Nov 2026)
php artisan migrate:fresh --seed

# Buat struktur star schema pada database Data Warehouse
php artisan migrate:fresh --database=dw --path=database/migrations/dw

# Isi Data Warehouse: jalankan job Pentaho database/dw/pentaho/job_etl_umkm_dw.kjb
# (Spoon, atau Kitchen; langkah lengkap di docs/SETUP_AND_RUN.md)

# Jalankan server
php artisan serve
```

Alternatif tanpa seeder: impor `database/dump/umkm_pakaian_oltp.sql` ke `umkm_pakaian` dan `database/dump/umkm_dw.sql` ke `umkm_dw`.
Pengecekan kualitas data OLTP: `mysql -uroot umkm_pakaian < database/audit_oltp.sql` (24 pemeriksaan, semua harus `PASS`).

---

## Akun Demo Bawaan (`PrototypeSeeder`)

| Peran (`tipe_akun`) | ID Akun | Username | Email | Password | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **ADMIN** | `ADM021` | `admin` | `admin@forma.local` | `admin123` | `AKTIF` |
| **ADMIN** | `ADM022` | `dinda_adm` | `dinda@forma.local` | `admin123` | `AKTIF` |
| **KASIR** | `KSR001` | `siti` | `siti.amalia@example.com` | `kasir123` | `AKTIF` |
| **KASIR** | `KSR002` | `rizky` | `rizky.ramadhan@example.com` | `kasir123` | `AKTIF` |
| **KASIR** | `KSR003` | `nabila` | `nabila.rahma@example.com` | `kasir123` | `NONAKTIF` |
| **KASIR** | `KSR004` | `bagas` | `bagas.pratama@example.com` | `kasir123` | `AKTIF` |
| **CUSTOMER** | `CUST123` | `budi` | `budi.santoso@example.com` | `budi123` | `AKTIF` |
| **CUSTOMER** | `CUST124` | `alya` | `alya.putri@example.com` | `alya123` | `AKTIF` |
| **CUSTOMER** | `CUST125` | `raka` | `raka.mahendra@example.com` | `raka123` | `AKTIF` |
| **CUSTOMER** | `CUST126` | `nadia` | `nadia.prameswari@example.com` | `nadia123` | `AKTIF` |

*(Plus `CUST201` s.d. `CUST356` dengan password default `customer123`.)*

---

## Pengujian Sistem (`PHPUnit`)

```bash
php artisan test
```
* **`tests/Feature/FinalSystemSmokeTest.php`**: Pengujian RBAC, restock pembelian (`tanggal_diterima` + `mutasi_stok`), validasi poin, checkout online/POS offline, serta penyimpanan `harga_normal`, `persen_diskon`, & `id_promo`.
* **`tests/Feature/DataWarehouseDashboardTest.php`**: skema DW tepat 3 fakta dan 6 dimensi, ETL tidak dijalankan dari Laravel, serta KPI dan drill-across dashboard dicocokkan dengan data DW yang nilainya diketahui.
* Rekonsiliasi ETL terhadap OLTP diuji oleh transformasi Pentaho `10_validasi_etl.ktr`; MDX vs SQL diuji oleh `database/dw/mondrian/run_mdx.ps1`. Hasilnya di [docs/VALIDATION_REPORT.md](docs/VALIDATION_REPORT.md).

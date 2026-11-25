# Setup dan Menjalankan FORMA (OLTP → ETL → DW → OLAP → Dashboard)

Lingkungan yang sudah diuji: Windows 11, **Laragon** (MySQL 8.0.30, port 3306, user `root` tanpa password), PHP 8.2 (Laravel 12 butuh PHP ≥ 8.2; PHP bawaan Laragon 8.1 tidak cukup), Composer, Java 21, Pentaho Data Integration 9.4 di `C:\pentaho\data-integration`, Schema Workbench di `C:\schema-workbench`.

> Hanya satu server MySQL boleh memakai port 3306. Jika XAMPP MySQL menyala, matikan dulu sebelum *Start All* di Laragon.

## 1. Sekali saja: siapkan proyek

```powershell
composer install
```

```powershell
copy .env.example .env
```

```powershell
php artisan key:generate
```

Pastikan `.env` berisi `DB_DATABASE=umkm_pakaian` dan `DB_DW_DATABASE=umkm_dw`.

## 2. Database

Buat kedua database (via HeidiSQL Laragon atau perintah ini):

```powershell
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS umkm_pakaian; CREATE DATABASE IF NOT EXISTS umkm_dw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

OLTP (tabel + data simulasi 12 bulan, deterministik):

```powershell
php artisan migrate:fresh --seed
```

Struktur DW (6 dimensi + 3 fakta + `etl_validasi`):

```powershell
php artisan migrate:fresh --database=dw --path=database/migrations/dw
```

Cek kualitas data OLTP (24 pemeriksaan harus PASS):

```powershell
Get-Content database\audit_oltp.sql | mysql -uroot umkm_pakaian
```

**Alternatif cepat tanpa seeder:** impor `database/dump/umkm_pakaian_oltp.sql` ke `umkm_pakaian` dan `database/dump/umkm_dw.sql` ke `umkm_dw`. Dump DW sudah berisi hasil ETL, tetapi tidak berisi tabel `migrations` Laravel.

## 3. ETL Pentaho

**Lewat Spoon (untuk demo):** jalankan `C:\pentaho\data-integration\Spoon.bat` → *File → Open* → `database\dw\pentaho\job_etl_umkm_dw.kjb` → *Run*. Job selesai ±2 detik.

**Lewat Kitchen (headless):**

```powershell
$proj = (Get-Location).Path; Push-Location C:\pentaho\data-integration; $env:SPOON_CONSOLE = "1"; .\Spoon.bat -main org.pentaho.di.kitchen.Kitchen -initialDir "$proj\database\dw\pentaho\" "/file:$proj\database\dw\pentaho\job_etl_umkm_dw.kjb" /level:Basic; Pop-Location
```

Jalankan dari folder proyek.

Cek hasil validasi (semua harus PASS):

```powershell
mysql -uroot umkm_dw -e "SELECT kode, pemeriksaan, status FROM etl_validasi"
```

## 4. OLAP (Mondrian)

Jalankan semua query MDX dan perbandingan MDX vs SQL:

```powershell
powershell -ExecutionPolicy Bypass -File database\dw\mondrian\run_mdx.ps1
```

Untuk demo GUI, buka skema di Schema Workbench; langkahnya ada di [OLAP_MDX_DOCUMENTATION.md](OLAP_MDX_DOCUMENTATION.md) bagian 4.

## 5. Aplikasi dan dashboard

```powershell
php artisan serve
```

Buka `http://127.0.0.1:8000/login`, masuk sebagai `admin` / `admin123`, lalu menu **Analitik DW** (`/admin/analitik`).

## 6. Tes otomatis

```powershell
php artisan test
```

## 7. Urutan jika data OLTP berubah

1. Ubah atau seed ulang OLTP.
2. Jalankan job Pentaho (full load, otomatis memvalidasi).
3. Refresh dashboard.

DW tidak perlu dimigrasi ulang kecuali struktur tabelnya berubah.

## 8. Masalah umum

| Gejala | Penyebab | Solusi |
| :--- | :--- | :--- |
| Dashboard: "Data Warehouse belum siap" | DW belum dimigrasi atau job belum dijalankan | Langkah 2 (migrasi DW) lalu langkah 3 |
| Job gagal di `10_validasi_etl` | Ada pemeriksaan FAIL | Lihat `etl_validasi`; perbaiki aturan transformasi, jangan ubah angkanya |
| Kitchen.bat error path | Path relatif di Kitchen.bat | Pakai perintah `Spoon.bat -main ...Kitchen` di atas |
| `Communications link failure` di Pentaho | MySQL belum jalan / port bentrok | *Start All* di Laragon; pastikan hanya satu MySQL di 3306 |
| Spoon lambat dibuka | JVM pertama kali | Buka Spoon 5 menit sebelum presentasi |

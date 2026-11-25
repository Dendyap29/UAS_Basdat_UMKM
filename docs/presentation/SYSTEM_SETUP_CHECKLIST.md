# Checklist Kesiapan Sistem (H-1 dan 30 menit sebelum demo)

| # | Cek | Cara | OK |
| :--- | :--- | :--- | :---: |
| 1 | Hanya satu MySQL di port 3306 | Matikan XAMPP; Laragon → *Start All* | ☐ |
| 2 | OLTP terisi | `mysql -uroot umkm_pakaian -e "SELECT COUNT(*) FROM penjualan"` → 3448 | ☐ |
| 3 | Audit OLTP | `Get-Content database\audit_oltp.sql \| mysql -uroot umkm_pakaian` → 24 PASS | ☐ |
| 4 | Job Pentaho berjalan | Kitchen/Spoon → semua entri `result=[true]` | ☐ |
| 5 | Validasi ETL | `SELECT status, COUNT(*) FROM umkm_dw.etl_validasi GROUP BY status` → PASS 20 | ☐ |
| 6 | MDX | `run_mdx.ps1` → 11 PASS, 0 FAIL | ☐ |
| 7 | Tes otomatis | `php artisan test` → 20 passed | ☐ |
| 8 | Aplikasi | `php artisan serve`, login `admin`/`admin123` | ☐ |
| 9 | Dashboard | `/admin/analitik` header "20/20 PASS", grafik tampil (butuh internet untuk Chart.js CDN) | ☐ |
| 10 | Spoon terbuka | `Spoon.bat`, job sudah dibuka di tab | ☐ |
| 11 | Schema Workbench terbuka | koneksi `umkm_dw` OK, skema terbuka, MDX D4 & D7 siap ditempel | ☐ |
| 12 | Backup | `database/dump/*.sql` + screenshot di flashdisk | ☐ |

Catatan Java: Pentaho 9.4 dan Schema Workbench berjalan di Java 21 laptop ini. Jika Spoon tidak mau terbuka, cek `java -version` dan variabel `PENTAHO_JAVA_HOME`.

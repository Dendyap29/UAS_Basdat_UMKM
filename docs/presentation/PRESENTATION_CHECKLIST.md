# Checklist Presentasi 16 Desember 2026

## Jadwal internal

| Tanggal | Target |
| :--- | :--- |
| 25 Nov | Desain final 3 fakta + implementasi DW, ETL, OLAP, dashboard (selesai) |
| s.d. 2 Des | Review kelompok, perbaikan bug, screenshot laporan per anggota |
| 5 Des | **Feature freeze**: tidak ada perubahan fakta/dimensi atau fitur baru |
| 6–12 Des | Laporan final, slide, 2× gladi demo penuh dengan stopwatch |
| 13–15 Des | Hanya perbaikan kritis, siapkan backup |
| 16 Des | Presentasi |

## Materi

- [ ] Slide 12 halaman sesuai [PRESENTATION_OUTLINE.md](PRESENTATION_OUTLINE.md)
- [ ] Pembagian pembicara per bagian demo ([DEMO_FLOW.md](DEMO_FLOW.md))
- [ ] Semua anggota membaca [VIVA_QUESTIONS.md](VIVA_QUESTIONS.md)
- [ ] Laporan memakai angka dan nama tabel dari desain final (3 fakta, 6 dimensi), bukan desain lama

## Cadangan jika gagal saat demo

| Masalah | Tindakan |
| :--- | :--- |
| MySQL Laragon tidak mau start | Port 3306 dipakai proses lain: tutup XAMPP / layanan MySQL lain, Start All ulang |
| Database rusak/kosong | Impor `database/dump/umkm_pakaian_oltp.sql` dan `umkm_dw.sql` (±1 menit) |
| Spoon lambat/error | Jalankan job lewat Kitchen di terminal; tunjukkan screenshot kanvas transformasi |
| Schema Workbench error | Jalankan `run_mdx.ps1` di terminal (hasil MDX + PASS); tunjukkan screenshot |
| Dashboard grafik kosong | Chart.js memakai CDN: pastikan internet/hotspot; angka KPI dan tabel tetap tampil tanpa internet |
| Port 8000 dipakai | `php artisan serve --port=8001` |
| Laptop mati | Laptop anggota lain dengan repo + dump yang sama; screenshot di flashdisk |

## Screenshot cadangan (ambil H-3)

- [ ] Spoon: job selesai + metrik step `07_fact_penjualan`
- [ ] `etl_validasi` 20 PASS
- [ ] Schema Workbench: skema + hasil MDX D4 dan D7
- [ ] Keluaran `run_mdx.ps1` (11 PASS)
- [ ] Dashboard: KPI, V2, V3 + varian diam, V5

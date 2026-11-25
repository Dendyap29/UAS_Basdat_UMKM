# Alur Demo (±15 menit)

Persiapan 10 menit sebelumnya: lihat [SYSTEM_SETUP_CHECKLIST.md](SYSTEM_SETUP_CHECKLIST.md). Buka lebih dulu Laragon (Start All), Spoon, Schema Workbench, terminal di folder proyek, dan browser yang sudah login admin.

| # | Menit | Tunjukkan | Kalimat kunci |
| :--- | :--- | :--- | :--- |
| 1 | 0:00–1:30 | Slide masalah | "FORMA menjual online dan di toko. Laporan OLTP hanya memberi angka total hari ini. Pemilik butuh tahu tren, penyebab, dan tindakan." |
| 2 | 1:30–3:00 | Aplikasi OLTP: POS kasir (1 transaksi contoh), menu Penjualan, Pembelian, Pengeluaran | "Ini sumber datanya: transaksi yang statusnya bisa berubah, tersebar di 14 tabel." |
| 3 | 3:00–4:00 | Slide star schema + bus matrix | "3 proses bisnis menjadi 3 fakta. Waktu dan produk dipakai bersama sehingga bisa drill-across." |
| 4 | 4:00–6:00 | Spoon: buka `job_etl_umkm_dw.kjb`, tunjukkan `07_fact_penjualan.ktr` (extract → 4 lookup → load), lalu *Run* job | "Full load ±2 detik. Langkah terakhir memvalidasi 20 angka; kalau ada yang beda, job berhenti sendiri." |
| 5 | 6:00–7:00 | HeidiSQL: `SELECT * FROM etl_validasi` (20 PASS), `SELECT COUNT(*) FROM fact_penjualan` | "Angka DW sama dengan OLTP: omzet Rp2.324.863.730 di kedua sisi." |
| 6 | 7:00–9:30 | Schema Workbench: skema, lalu MDX D4 (drill-down Jaket → produk → varian) dan D7 (pivot ukuran × warna); atau terminal `run_mdx.ps1` (11 PASS) | "Ini operasi OLAP nyata, dan hasil MDX dicocokkan dengan SQL." |
| 7 | 9:30–13:00 | Dashboard `/admin/analitik`: KPI → V2 (kas vs laba) → klik kategori di V3 → daftar varian diam → V5 promo | "Laba Rp828 jt tetapi kas Rp442 jt. Selisihnya Rp386 jt tertahan di stok, dan 13 varian (kebanyakan XXL) diam lebih dari 90 hari. Promo diskon 30% hanya bermargin 15%." |
| 8 | 13:00–14:00 | Slide rekomendasi | "Tahan restock XXL, batasi diskon promo, tawarkan member ke guest di kasir." |
| 9 | 14:00–15:00 | Slide validasi & keterbatasan | "24 audit OLTP, 20 rekonsiliasi ETL, 11 MDX vs SQL, 20 tes otomatis, semuanya PASS. Data adalah simulasi." |

## Insight utama yang dibawakan

Hanya satu, supaya fokus: **uang FORMA tertahan di stok**.
- **Bukti:** V2 (laba vs kas) dan V3 (varian diam).
- **Rekomendasi:** batasi pembelian varian yang perputarannya lambat.

## Jika ada yang gagal saat demo

Lihat [PRESENTATION_CHECKLIST.md](PRESENTATION_CHECKLIST.md) bagian cadangan.

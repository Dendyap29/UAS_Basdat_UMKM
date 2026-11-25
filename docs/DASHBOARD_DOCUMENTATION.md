# Dokumentasi Dashboard Analitik

Halaman: `/admin/analitik` (khusus Admin). Controller: `app/Http/Controllers/AnalyticsController.php`. View: `resources/views/admin/analitik/index.blade.php`.
Dashboard **hanya membaca DW** (`umkm_dw`). Tidak ada angka yang ditulis manual: KPI, grafik, dan teks insight dihitung dari query setiap kali halaman dibuka.

## 1. Alur cerita (1 halaman)

```
Header: sumber data, waktu muat Pentaho, hasil validasi ETL (mis. 20/20 PASS)
Filter global: Tahun, Kuartal
KPI: Omzet · Laba kotor (margin) · Laba operasional · Arus kas operasional
A. Kesehatan usaha   → V1 tren, V2 uang masuk vs keluar
B. Produk dan stok   → V3 kinerja produk + varian diam, V4 ukuran × warna
C. Strategi penjualan → V5 promo, V6 member vs guest + poin, V7 pola hari
D. Uang keluar       → V8a pembelian per supplier, V8b biaya operasional per jenis
```

## 2. Visual

| # | Pertanyaan bisnis | Fakta × dimensi | Measure | Jenis grafik dan alasannya | OLAP | Keputusan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| V1 | Apakah usaha tumbuh, kapan puncaknya? | penjualan × waktu | omzet, laba kotor | garis: tren waktu | roll-up/drill-down (tombol Bulan/Kuartal) | waktu pembelian stok musiman |
| V2 | Kenapa kas lebih kecil dari laba? | 3 fakta × waktu | omzet, pembelian, biaya, arus kas | batang bertumpuk + garis: komposisi uang keluar vs masuk per bulan | drill-across | batas pembelian bulanan |
| V3 | Produk mana laku/untung dan mana yang menumpuk? | penjualan + pembelian × produk | unit terjual, omzet, margin, unit dibeli, tanggal jual terakhir | tabel: perlu banyak angka per baris, bisa diurutkan terlaris/paling lambat | drill-down Kategori → Produk → Varian, drill-across | restock atau cuci gudang |
| V3 (bawah) | Varian apa yang diam ≥ 90 hari tetapi masih bersisa? | penjualan + pembelian × produk | sisa unit, hari tanpa penjualan | tabel daftar tindakan | drill-across | hentikan restock |
| V4 | Ukuran/warna apa yang dicari? | penjualan × produk | unit terjual | heatmap pivot | pivot | komposisi pembelian |
| V5 | Promo mana yang sehat? | penjualan × promo | transaksi, omzet, biaya diskon, margin, omzet per hari aktif | batang horizontal margin per promo (garis pembanding tanpa promo) + tabel | slice | aturan promo |
| V6 | Seberapa besar guest dan apakah poin dipakai? | penjualan × pelanggan × transaksi | omzet, poin didapat/ditukar | batang bertumpuk per kanal + tabel member (drill ke pelanggan) | dice | program member |
| V7 | Kapan toko ramai? | penjualan × waktu (hari) × transaksi | rata-rata transaksi per hari kalender | batang bertumpuk per hari | pivot | jadwal kasir |
| V8a | Ke supplier mana uang pembelian mengalir? | pembelian × supplier | total pembelian | batang horizontal | – | negosiasi |
| V8b | Biaya operasional terbesar di pos apa? | pengeluaran (jenis) | nominal | donat: komposisi | – | kontrol biaya |

## 3. Filter dan interaksi

| Kontrol | Cakupan | Alasan |
| :--- | :--- | :--- |
| Tahun, Kuartal | global | ketiga fakta punya `dim_waktu`, jadi slice berlaku konsisten |
| Klik kategori/produk di V3 (`kat`, `produk`) | V3 dan V4 | hanya fakta penjualan dan pembelian yang punya produk; pengeluaran tidak, jadi tidak dijadikan filter global |
| Bulan/Kuartal di V1 (`level`) | V1 | demonstrasi roll-up/drill-down |
| Terlaris/Paling lambat (`urut`) | V3 | Top-N vs Bottom-N |

Tidak ada filter kanal global karena pengeluaran dan pembelian tidak punya kanal. Kanal ditampilkan sebagai sumbu di V6 dan V7.

## 4. Aturan perhitungan

- Rasio (margin, rata-rata transaksi) dihitung dari total, bukan rata-rata baris.
- Jumlah transaksi = `COUNT(DISTINCT id_penjualan)` karena grain fakta per baris barang.
- Drill-across: setiap fakta diagregasi sendiri per bulan/per produk, lalu digabung di PHP. Fakta tidak pernah di-JOIN langsung.
- "Dibeli − terjual" di V3 mengikuti periode filter. Untuk seluruh periode nilainya sama dengan stok OLTP, kecuali penyesuaian opname −13 unit (validasi V15).
- Daftar varian diam selalu memakai seluruh periode, karena yang dinilai adalah posisi saat ini.
- Omzet per hari aktif promo = omzet ÷ jumlah hari periode promo yang berada dalam periode filter dan rentang data.

## 5. Contoh insight yang muncul (seluruh periode)

- V1: omzet tertinggi Des 2025 (Rp242.075.100), terendah Jun 2026 (Rp164.259.500).
- V2: laba operasional Rp828.080.607 vs arus kas Rp441.551.207; selisih Rp386.529.400 adalah pembelian yang belum menjadi HPP. Arus kas negatif pada Des 2025 karena pembelian stok awal.
- V3: 13 varian masih bersisa (104 unit) tetapi tidak terjual ≥ 90 hari, sebagian besar ukuran XXL.
- V5: margin tanpa promo 40,9%; terendah Twin-Date Flash Promo 11.11 (diskon 30%) 15,3%. Analisis ini deskriptif dan tidak membuktikan promo menaikkan penjualan.
- V6: 642 dari 1.902 transaksi toko (33,8%) dilakukan guest; poin yang ditukar baru 5,3%.

## 6. Pengujian

`tests/Feature/DataWarehouseDashboardTest.php` memuat data DW kecil yang nilainya diketahui, lalu memastikan:
- KPI tepat, termasuk COUNT DISTINCT nota dan identitas laba − kas.
- Filter periode bekerja.
- Drill-down kategori → produk → varian bekerja.
- Pesan "Data Warehouse belum siap" muncul jika DW kosong.

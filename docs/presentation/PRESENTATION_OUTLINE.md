# Kerangka Presentasi (12 slide)

Satu gagasan per slide. Angka dari data simulasi.

1. **Konteks**: FORMA, UMKM fashion, jual online (web, QR) dan toko (POS, QR/tunai), program member dan promo.
2. **Masalah**: laporan OLTP hanya total hari ini. Pertanyaan pemilik: apakah usaha sehat dan uangnya ke mana, produk/stok apa yang perlu ditindak, promo dan member mana yang berhasil.
3. **Tujuan DW**: data historis yang final dan siap diringkas untuk 3 tujuan: kesehatan usaha, produk & stok, strategi penjualan.
4. **OLTP**: 14 tabel, aturan bisnis, 24/24 audit PASS. Diagram ringkas header–detail penjualan dan pembelian.
5. **Arsitektur**: OLTP → Pentaho (satu jalur, full load, validasi) → DW → Mondrian & dashboard.
6. **Tiga fakta**: penjualan, pembelian, pengeluaran operasional. Grain masing-masing. Kenapa tidak ada fakta stok (bisa diturunkan, V15) dan pesanan gagal (tidak dicampur dengan omzet).
7. **Enam dimensi**: tabel keputusan. Kenapa promo berdiri sendiri (junk naik 3 → 30 kombinasi, periode tumpang tindih), kenapa kasir dihapus (beban 629/632/641).
8. **Star schema + bus matrix**: conformed waktu & produk, junk `dim_transaksi`, degenerate `id_penjualan` & `jenis_pengeluaran`.
9. **ETL**: urutan job 00–10, alokasi poin, HPP tertimbang, validasi + Abort.
10. **OLAP**: virtual cube Keuangan; contoh roll-up, drill-down, slice, dice, pivot, drill-across; MDX = SQL (11/11).
11. **Insight & rekomendasi**: laba Rp828 jt vs kas Rp442 jt (selisih = stok); 13 varian diam; margin promo 15–34% vs 41%; guest 33,8% transaksi toko; poin ditukar 5,3%.
12. **Validasi, keterbatasan, penutup**: ringkasan PASS; data simulasi, HPP rata-rata, full load; pengembangan berikutnya (fakta pesanan online, incremental load).

-- ============================================================================
-- QUERY OLAP (SQL) DATA WAREHOUSE FORMA - padanan SQL dari olap_mdx_queries.mdx
-- Database: umkm_dw  |  Jalankan: mysql -uroot umkm_dw < database/dw/olap_queries.sql
-- Validasi rekonsiliasi OLTP vs DW ada di database/dw/validasi_etl.sql.
-- Aturan drill-across: agregasi tiap fakta sendiri pada dimensi bersama, baru digabung.
-- ============================================================================
USE umkm_dw;

-- 1. ROLL-UP: bulan -> kuartal -> tahun (WITH ROLLUP menghasilkan subtotal tiap level)
SELECT w.tahun, w.kuartal, SUM(f.pendapatan_bersih) AS omzet, SUM(f.laba_kotor) AS laba_kotor,
       ROUND(SUM(f.laba_kotor) / SUM(f.pendapatan_bersih) * 100, 1) AS margin_pct
FROM fact_penjualan f JOIN dim_waktu w ON w.waktu_key = f.waktu_key
GROUP BY w.tahun, w.kuartal WITH ROLLUP;

-- 2. DRILL-DOWN waktu: kuartal 4 tahun 2026 -> bulan
SELECT w.bulan, w.nama_bulan, SUM(f.pendapatan_bersih) AS omzet, COUNT(DISTINCT f.id_penjualan) AS transaksi
FROM fact_penjualan f JOIN dim_waktu w ON w.waktu_key = f.waktu_key
WHERE w.tahun = 2026 AND w.kuartal = 4
GROUP BY w.bulan, w.nama_bulan ORDER BY w.bulan;

-- 3. DRILL-DOWN produk: kategori -> produk (Jaket) -> varian
SELECT p.nama_kategori, SUM(f.jumlah) AS unit, SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f JOIN dim_produk p ON p.produk_key = f.produk_key
GROUP BY p.nama_kategori ORDER BY omzet DESC;

SELECT p.nama_produk, SUM(f.jumlah) AS unit, SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f JOIN dim_produk p ON p.produk_key = f.produk_key
WHERE p.nama_kategori = 'Jaket'
GROUP BY p.id_produk, p.nama_produk ORDER BY omzet DESC;

SELECT p.ukuran, p.warna, SUM(f.jumlah) AS unit, SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f JOIN dim_produk p ON p.produk_key = f.produk_key
WHERE p.nama_produk = 'Corduroy Harrington Jacket'
GROUP BY p.id_varian, p.ukuran, p.warna ORDER BY p.warna, FIELD(p.ukuran, 'S', 'M', 'L', 'XL', 'XXL');

-- 4. SLICE: satu dimensi dikunci (kanal ONLINE)
SELECT p.nama_kategori, SUM(f.pendapatan_bersih) AS omzet, COUNT(DISTINCT f.id_penjualan) AS transaksi
FROM fact_penjualan f
JOIN dim_produk p ON p.produk_key = f.produk_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
WHERE t.kanal_penjualan = 'ONLINE'
GROUP BY p.nama_kategori;

-- 5. DICE: beberapa dimensi dibatasi (Q1 2026, kategori Kaos & Kemeja, per kanal)
SELECT p.nama_kategori, t.kanal_penjualan, SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_key
JOIN dim_produk p ON p.produk_key = f.produk_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
WHERE w.tahun = 2026 AND w.kuartal = 1 AND p.nama_kategori IN ('Kaos', 'Kemeja')
GROUP BY p.nama_kategori, t.kanal_penjualan;

-- 6. PIVOT: ukuran (baris) x warna (kolom), unit terjual
SELECT p.ukuran,
       SUM(CASE WHEN p.warna = 'hitam' THEN f.jumlah ELSE 0 END) AS hitam,
       SUM(CASE WHEN p.warna = 'putih' THEN f.jumlah ELSE 0 END) AS putih,
       SUM(CASE WHEN p.warna = 'navy' THEN f.jumlah ELSE 0 END) AS navy,
       SUM(CASE WHEN p.warna = 'khaki' THEN f.jumlah ELSE 0 END) AS khaki,
       SUM(CASE WHEN p.warna = 'olive' THEN f.jumlah ELSE 0 END) AS olive,
       SUM(CASE WHEN p.warna = 'abu-abu' THEN f.jumlah ELSE 0 END) AS abu_abu,
       SUM(f.jumlah) AS total
FROM fact_penjualan f JOIN dim_produk p ON p.produk_key = f.produk_key
GROUP BY p.ukuran ORDER BY FIELD(p.ukuran, 'S', 'M', 'L', 'XL', 'XXL');

-- 7. DRILL-ACROSS waktu: uang masuk vs uang keluar per bulan (3 fakta, agregasi terpisah)
SELECT k.tahun, k.bulan,
       COALESCE(j.omzet, 0) AS omzet, COALESCE(b.pembelian, 0) AS pembelian, COALESCE(e.operasional, 0) AS operasional,
       COALESCE(j.laba, 0) - COALESCE(e.operasional, 0) AS laba_operasional,
       COALESCE(j.omzet, 0) - COALESCE(b.pembelian, 0) - COALESCE(e.operasional, 0) AS arus_kas
FROM (SELECT DISTINCT tahun, bulan FROM dim_waktu) k
LEFT JOIN (SELECT w.tahun, w.bulan, SUM(f.pendapatan_bersih) omzet, SUM(f.laba_kotor) laba
           FROM fact_penjualan f JOIN dim_waktu w ON w.waktu_key = f.waktu_key GROUP BY w.tahun, w.bulan) j
       ON j.tahun = k.tahun AND j.bulan = k.bulan
LEFT JOIN (SELECT w.tahun, w.bulan, SUM(b.total_biaya) pembelian
           FROM fact_pembelian b JOIN dim_waktu w ON w.waktu_key = b.waktu_key GROUP BY w.tahun, w.bulan) b
       ON b.tahun = k.tahun AND b.bulan = k.bulan
LEFT JOIN (SELECT w.tahun, w.bulan, SUM(e.nominal) operasional
           FROM fact_pengeluaran e JOIN dim_waktu w ON w.waktu_key = e.waktu_key GROUP BY w.tahun, w.bulan) e
       ON e.tahun = k.tahun AND e.bulan = k.bulan
ORDER BY k.tahun, k.bulan;

-- 8. DRILL-ACROSS produk: unit dibeli vs unit terjual per kategori (lewat dim_produk yang conformed)
SELECT p.nama_kategori, COALESCE(b.unit_dibeli, 0) AS unit_dibeli, COALESCE(j.unit_terjual, 0) AS unit_terjual,
       COALESCE(b.unit_dibeli, 0) - COALESCE(j.unit_terjual, 0) AS selisih
FROM (SELECT DISTINCT nama_kategori FROM dim_produk) p
LEFT JOIN (SELECT d.nama_kategori, SUM(f.jumlah) unit_terjual FROM fact_penjualan f
           JOIN dim_produk d ON d.produk_key = f.produk_key GROUP BY d.nama_kategori) j ON j.nama_kategori = p.nama_kategori
LEFT JOIN (SELECT d.nama_kategori, SUM(b.jumlah) unit_dibeli FROM fact_pembelian b
           JOIN dim_produk d ON d.produk_key = b.produk_key GROUP BY d.nama_kategori) b ON b.nama_kategori = p.nama_kategori
ORDER BY p.nama_kategori;

-- 9. Efektivitas promo (slice per promo)
SELECT pr.nama_promo, pr.persen_diskon, COUNT(DISTINCT f.id_penjualan) AS transaksi,
       SUM(f.pendapatan_bersih) AS omzet, SUM(f.diskon_promo) AS diskon,
       ROUND(SUM(f.laba_kotor) / SUM(f.pendapatan_bersih) * 100, 1) AS margin_pct
FROM fact_penjualan f JOIN dim_promo pr ON pr.promo_key = f.promo_key
GROUP BY pr.promo_key, pr.nama_promo, pr.persen_diskon ORDER BY margin_pct DESC;

-- 10. Member vs guest per kanal (dice) dan poin
SELECT t.kanal_penjualan, c.tipe_pelanggan, COUNT(DISTINCT f.id_penjualan) AS transaksi,
       SUM(f.pendapatan_bersih) AS omzet, SUM(f.poin_didapat) AS poin_didapat, SUM(f.poin_ditukar) AS poin_ditukar
FROM fact_penjualan f
JOIN dim_pelanggan c ON c.pelanggan_key = f.pelanggan_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
GROUP BY t.kanal_penjualan, c.tipe_pelanggan;

-- 11. Pola hari x kanal (pivot), rata-rata transaksi per hari kalender
SELECT w.hari_dalam_minggu, w.nama_hari,
       ROUND(COUNT(DISTINCT CASE WHEN t.kanal_penjualan = 'ONLINE' THEN f.id_penjualan END) / h.n, 1) AS online_per_hari,
       ROUND(COUNT(DISTINCT CASE WHEN t.kanal_penjualan = 'OFFLINE' THEN f.id_penjualan END) / h.n, 1) AS offline_per_hari
FROM fact_penjualan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
JOIN (SELECT hari_dalam_minggu, COUNT(*) n FROM dim_waktu GROUP BY hari_dalam_minggu) h ON h.hari_dalam_minggu = w.hari_dalam_minggu
GROUP BY w.hari_dalam_minggu, w.nama_hari, h.n ORDER BY w.hari_dalam_minggu;

-- 12. Varian diam: masih ada sisa (dibeli - terjual > 0) tetapi tidak terjual >= 90 hari
SELECT p.nama_kategori, p.nama_produk, p.ukuran, p.warna,
       COALESCE(b.unit, 0) - COALESCE(j.unit, 0) AS sisa, j.terakhir,
       DATEDIFF((SELECT MAX(w.tanggal) FROM fact_penjualan f JOIN dim_waktu w ON w.waktu_key = f.waktu_key), j.terakhir) AS hari_diam
FROM dim_produk p
LEFT JOIN (SELECT f.produk_key, SUM(f.jumlah) unit, MAX(w.tanggal) terakhir FROM fact_penjualan f
           JOIN dim_waktu w ON w.waktu_key = f.waktu_key GROUP BY f.produk_key) j ON j.produk_key = p.produk_key
LEFT JOIN (SELECT produk_key, SUM(jumlah) unit FROM fact_pembelian GROUP BY produk_key) b ON b.produk_key = p.produk_key
HAVING sisa > 0 AND (terakhir IS NULL OR hari_diam >= 90)
ORDER BY hari_diam DESC;

-- 13. Uang keluar: pembelian per supplier dan biaya operasional per jenis
SELECT s.kota, s.nama_supplier, SUM(b.total_biaya) AS total, SUM(b.jumlah) AS unit,
       ROUND(SUM(b.total_biaya) / SUM(b.jumlah)) AS harga_beli_rata2_tertimbang
FROM fact_pembelian b JOIN dim_supplier s ON s.supplier_key = b.supplier_key
GROUP BY s.kota, s.nama_supplier ORDER BY total DESC;

SELECT jenis_pengeluaran, SUM(nominal) AS total, COUNT(*) AS catatan
FROM fact_pengeluaran GROUP BY jenis_pengeluaran ORDER BY total DESC;

-- ============================================================================
-- KUMPULAN QUERY VALIDASI (REKONSILIASI OLTP vs DW) & OPERASI OLAP (Q1 - Q12)
-- Database OLTP : umkm_pakaian
-- Database DW   : umkm_dw
-- ============================================================================


-- ============================================================================
-- BAGIAN 1: VALIDASI & REKONSILIASI OLTP vs DATA WAREHOUSE (BAB VII)
-- ============================================================================

-- 1.1 Rekonsiliasi Penjualan Valid (Jumlah baris, subtotal, dan total bayar)
SELECT 'OLTP' AS sumber,
       COUNT(*) AS jml_baris,
       COUNT(DISTINCT d.id_penjualan) AS jml_transaksi,
       ROUND(SUM(d.jumlah * d.harga_satuan), 2) AS total_subtotal
FROM umkm_pakaian.detail_penjualan d
JOIN umkm_pakaian.penjualan p ON p.id_penjualan = d.id_penjualan
WHERE p.status_pembayaran = 'BERHASIL'
  AND p.status_penjualan <> 'BATAL'
UNION ALL
SELECT 'DW' AS sumber,
       COUNT(*) AS jml_baris,
       COUNT(DISTINCT id_penjualan) AS jml_transaksi,
       ROUND(SUM(subtotal), 2) AS total_subtotal
FROM umkm_dw.fact_penjualan;

-- 1.2 Rekonsiliasi Pendapatan Bersih DW vs Nominal Bayar OLTP
SELECT 'OLTP' AS sumber, ROUND(SUM(nominal_bayar), 2) AS total_bersih
FROM umkm_pakaian.penjualan
WHERE status_pembayaran = 'BERHASIL'
  AND status_penjualan <> 'BATAL'
UNION ALL
SELECT 'DW' AS sumber, ROUND(SUM(pendapatan_bersih), 2) AS total_bersih
FROM umkm_dw.fact_penjualan;

-- 1.3 Rekonsiliasi Pembelian DITERIMA (OLTP vs fact_pembelian)
SELECT 'OLTP' AS sumber,
       COUNT(*) AS jml_baris,
       ROUND(SUM(dp.jumlah * dp.harga_satuan), 2) AS total_biaya
FROM umkm_pakaian.detail_pembelian dp
JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = dp.id_pembelian
WHERE pb.status_pembelian = 'DITERIMA'
UNION ALL
SELECT 'DW' AS sumber,
       COUNT(*) AS jml_baris,
       ROUND(SUM(total_biaya), 2) AS total_biaya
FROM umkm_dw.fact_pembelian;

-- 1.4 Rekonsiliasi Pengeluaran (OLTP vs fact_pengeluaran)
SELECT 'OLTP' AS sumber, COUNT(*) AS jml_baris, ROUND(SUM(nominal), 2) AS total_nominal
FROM umkm_pakaian.pengeluaran
UNION ALL
SELECT 'DW' AS sumber, COUNT(*) AS jml_baris, ROUND(SUM(nominal), 2) AS total_nominal
FROM umkm_dw.fact_pengeluaran;

-- 1.5 Cek Integritas Key (Tidak boleh ada NULL dan cek distribusi baris key 0)
SELECT
    SUM(waktu_key IS NULL OR produk_key IS NULL OR transaksi_key IS NULL) AS key_null,
    SUM(customer_key = 0) AS baris_guest,
    SUM(staf_key = 0) AS baris_online_tanpa_kasir,
    SUM(promo_key = 0) AS baris_tanpa_promo
FROM umkm_dw.fact_penjualan;


-- ============================================================================
-- BAGIAN 2: OPERASI OLAP MULTIDIMENSI (BAB V & VII)
-- ============================================================================

USE umkm_dw;

-- 2.1 ROLL-UP (Harian -> Bulanan -> Kuartalan -> Tahunan dengan WITH ROLLUP)
SELECT w.tahun, w.kuartal, w.bulan, w.nama_bulan,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS total_unit,
       SUM(f.pendapatan_bersih) AS omzet,
       SUM(f.laba_kotor) AS laba_kotor
FROM fact_penjualan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_key
GROUP BY w.tahun, w.kuartal, w.bulan, w.nama_bulan
ORDER BY w.tahun, w.bulan;

SELECT w.tahun, w.kuartal,
       SUM(f.pendapatan_bersih) AS omzet,
       SUM(f.laba_kotor) AS laba_kotor
FROM fact_penjualan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_key
GROUP BY w.tahun, w.kuartal WITH ROLLUP;

-- 2.2 DRILL-DOWN (Kategori -> Produk -> Ukuran)
SELECT p.nama_kategori, p.nama_produk, p.ukuran,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
JOIN dim_produk p ON p.produk_key = f.produk_key
WHERE p.nama_kategori = 'Kemeja'
GROUP BY p.nama_kategori, p.nama_produk, p.ukuran
ORDER BY qty DESC;

-- 2.3 SLICE (Memotong pada 1 dimensi: Kanal ONLINE saja)
SELECT w.tahun, w.bulan, w.nama_bulan,
       COUNT(DISTINCT f.id_penjualan) AS trx_online,
       SUM(f.pendapatan_bersih) AS omzet_online
FROM fact_penjualan f
JOIN dim_waktu w     ON w.waktu_key = f.waktu_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
WHERE t.kanal_penjualan = 'ONLINE'
GROUP BY w.tahun, w.bulan, w.nama_bulan
ORDER BY w.tahun, w.bulan;

-- 2.4 DICE (Memotong beberapa dimensi: Q1, kategori Kaos & Kemeja, kanal OFFLINE)
SELECT p.nama_kategori, w.tahun, w.nama_bulan,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet,
       SUM(f.laba_kotor) AS laba
FROM fact_penjualan f
JOIN dim_waktu w     ON w.waktu_key = f.waktu_key
JOIN dim_produk p    ON p.produk_key = f.produk_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
WHERE w.kuartal = 1
  AND p.nama_kategori IN ('Kaos', 'Kemeja')
  AND t.kanal_penjualan = 'OFFLINE'
GROUP BY p.nama_kategori, w.tahun, w.bulan, w.nama_bulan
ORDER BY w.tahun, w.bulan, p.nama_kategori;

-- 2.5 PIVOT (Omzet Kategori x Kanal Penjualan)
SELECT p.nama_kategori,
       SUM(CASE WHEN t.kanal_penjualan = 'ONLINE'  THEN f.pendapatan_bersih ELSE 0 END) AS omzet_online,
       SUM(CASE WHEN t.kanal_penjualan = 'OFFLINE' THEN f.pendapatan_bersih ELSE 0 END) AS omzet_offline,
       SUM(f.pendapatan_bersih) AS total_omzet
FROM fact_penjualan f
JOIN dim_produk p    ON p.produk_key = f.produk_key
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
GROUP BY p.nama_kategori
ORDER BY total_omzet DESC;


-- ============================================================================
-- BAGIAN 3: QUERY ANALISIS PERTANYAAN BISNIS (Q1 - Q12, BAB VIII)
-- ============================================================================

-- Q3: Kombinasi Ukuran x Warna Terlaris per Kategori
SELECT p.nama_kategori, p.ukuran, p.warna,
       SUM(f.jumlah) AS qty_terjual,
       SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
JOIN dim_produk p ON p.produk_key = f.produk_key
GROUP BY p.nama_kategori, p.ukuran, p.warna
ORDER BY qty_terjual DESC
LIMIT 15;

-- Q4: Perbandingan Kanal & Metode Pembayaran
SELECT t.kanal_penjualan, t.metode_pembayaran,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
JOIN dim_transaksi t ON t.transaksi_key = f.transaksi_key
GROUP BY t.kanal_penjualan, t.metode_pembayaran
ORDER BY omzet DESC;

-- Q5: Efektivitas Promo (Penjualan saat Promo vs Tanpa Promo & Total Diskon)
SELECT pr.nama_promo, pr.kelompok_diskon,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet,
       SUM(f.diskon_promo) AS total_diskon_promo
FROM fact_penjualan f
JOIN dim_promo pr ON pr.promo_key = f.promo_key
GROUP BY pr.promo_key, pr.nama_promo, pr.kelompok_diskon
ORDER BY omzet DESC;

-- Q6: Segmentasi Member vs Guest & Top 10 Customer
SELECT CASE WHEN f.customer_key = 0 THEN 'GUEST (Non-member)' ELSE 'MEMBER' END AS segmen,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
GROUP BY CASE WHEN f.customer_key = 0 THEN 'GUEST (Non-member)' ELSE 'MEMBER' END;

SELECT c.id_akun, c.nama,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS total_belanja
FROM fact_penjualan f
JOIN dim_customer c ON c.customer_key = f.customer_key
WHERE f.customer_key > 0
GROUP BY c.customer_key, c.id_akun, c.nama
ORDER BY total_belanja DESC
LIMIT 10;

-- Q7: Kinerja Kasir POS
SELECT s.id_akun, s.nama,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet_pos
FROM fact_penjualan f
JOIN dim_staf s ON s.staf_key = f.staf_key
WHERE f.staf_key > 0
GROUP BY s.staf_key, s.id_akun, s.nama
ORDER BY omzet_pos DESC;

-- Q8: Hari Teramai dalam Seminggu (Senin - Minggu)
SELECT w.hari_dalam_minggu, w.nama_hari, w.is_akhir_pekan,
       COUNT(DISTINCT f.id_penjualan) AS jml_transaksi,
       SUM(f.jumlah) AS qty,
       SUM(f.pendapatan_bersih) AS omzet
FROM fact_penjualan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_key
GROUP BY w.hari_dalam_minggu, w.nama_hari, w.is_akhir_pekan
ORDER BY w.hari_dalam_minggu;

-- Q9: Pembelian Produk per Supplier
SELECT s.id_supplier, s.nama_supplier,
       COUNT(DISTINCT fp.id_pembelian) AS jml_po,
       SUM(fp.jumlah) AS qty_dibeli,
       SUM(fp.total_biaya) AS total_biaya_pembelian
FROM fact_pembelian fp
JOIN dim_supplier s ON s.supplier_key = fp.supplier_key
GROUP BY s.supplier_key, s.id_supplier, s.nama_supplier
ORDER BY total_biaya_pembelian DESC;

-- Q10: Komposisi Pengeluaran per Jenis
SELECT jp.kelompok, jp.jenis_pengeluaran,
       COUNT(*) AS jml_pencatatan,
       SUM(fe.nominal) AS total_nominal
FROM fact_pengeluaran fe
JOIN dim_jenis_pengeluaran jp ON jp.jenis_pengeluaran_key = fe.jenis_pengeluaran_key
GROUP BY jp.kelompok, jp.jenis_pengeluaran
ORDER BY total_nominal DESC;

-- Q11: DRILL-ACROSS Arus Kas Bulanan (Pemasukan fact_penjualan vs Pengeluaran fact_pengeluaran)
SELECT per.tahun, per.bulan, per.nama_bulan,
       COALESCE(pj.pemasukan, 0) AS pemasukan,
       COALESCE(pg.pengeluaran, 0) AS pengeluaran,
       COALESCE(pj.pemasukan, 0) - COALESCE(pg.pengeluaran, 0) AS arus_kas
FROM (
    SELECT DISTINCT w.tahun, w.bulan, w.nama_bulan
    FROM dim_waktu w
    WHERE w.waktu_key IN (
        SELECT waktu_key FROM fact_penjualan
        UNION
        SELECT waktu_key FROM fact_pengeluaran
    )
) per
LEFT JOIN (
    SELECT w.tahun, w.bulan, SUM(f.pendapatan_bersih) AS pemasukan
    FROM fact_penjualan f
    JOIN dim_waktu w ON w.waktu_key = f.waktu_key
    GROUP BY w.tahun, w.bulan
) pj ON pj.tahun = per.tahun AND pj.bulan = per.bulan
LEFT JOIN (
    SELECT w.tahun, w.bulan, SUM(fe.nominal) AS pengeluaran
    FROM fact_pengeluaran fe
    JOIN dim_waktu w ON w.waktu_key = fe.waktu_key
    GROUP BY w.tahun, w.bulan
) pg ON pg.tahun = per.tahun AND pg.bulan = per.bulan
ORDER BY per.tahun, per.bulan;

-- Q12: Varian dengan Stok di Bawah Minimum pada Snapshot Terbaru
SELECT p.id_varian, p.nama_produk, p.nama_kategori, p.ukuran, p.warna,
       fs.stok, fs.stok_minimum
FROM fact_stok_snapshot fs
JOIN dim_produk p ON p.produk_key = fs.produk_key
WHERE fs.waktu_key = (SELECT MAX(waktu_key) FROM fact_stok_snapshot)
  AND fs.is_low_stock = 1
ORDER BY fs.stok ASC;

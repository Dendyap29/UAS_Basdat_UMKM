-- ============================================================================
-- VALIDASI ETL TABEL FAKTA (pelengkap olap_queries.sql bagian 1)
-- Database OLTP : umkm_pakaian
-- Database DW   : umkm_dw
-- Setiap query mengembalikan kolom `hasil` = PASS atau FAIL beserta angkanya.
-- ============================================================================

-- V1 Rumus measure fact_penjualan per baris
SELECT 'V1 rumus measure penjualan' AS cek,
       SUM(ABS(subtotal - ROUND(jumlah * harga_satuan, 2)) > 0.01)                    AS salah_subtotal,
       SUM(ABS(diskon_promo - ROUND((harga_normal - harga_satuan) * jumlah, 2)) > 0.01) AS salah_diskon_promo,
       SUM(ABS(pendapatan_bersih - (subtotal - diskon_poin)) > 0.01)                   AS salah_pendapatan,
       SUM(ABS(laba_kotor - (pendapatan_bersih - hpp)) > 0.01)                         AS salah_laba,
       IF(SUM(ABS(subtotal - ROUND(jumlah * harga_satuan, 2)) > 0.01
            OR ABS(diskon_promo - ROUND((harga_normal - harga_satuan) * jumlah, 2)) > 0.01
            OR ABS(pendapatan_bersih - (subtotal - diskon_poin)) > 0.01
            OR ABS(laba_kotor - (pendapatan_bersih - hpp)) > 0.01) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_penjualan;

-- V2 Pendapatan bersih per transaksi DW vs nominal_bayar OLTP
SELECT 'V2 pendapatan per transaksi vs nominal_bayar' AS cek,
       COUNT(*)                          AS jml_transaksi,
       SUM(ABS(selisih) >= 0.01)         AS transaksi_selisih,
       MAX(ABS(selisih))                 AS selisih_terbesar,
       ROUND(SUM(selisih), 2)            AS selisih_total,
       IF(MAX(ABS(selisih)) < 0.05, 'PASS', 'FAIL') AS hasil
FROM (
  SELECT p.id_penjualan, COALESCE(f.bersih, 0) - p.nominal_bayar AS selisih
  FROM umkm_pakaian.penjualan p
  LEFT JOIN (SELECT id_penjualan, SUM(pendapatan_bersih) AS bersih
             FROM umkm_dw.fact_penjualan GROUP BY id_penjualan) f ON f.id_penjualan = p.id_penjualan
  WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'
) x;

-- V3 Tidak ada transaksi tidak valid (gagal bayar / batal / menunggu) yang masuk fakta
SELECT 'V3 transaksi tidak valid di fakta' AS cek,
       COUNT(*) AS baris_tidak_valid,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_penjualan f
JOIN umkm_pakaian.penjualan p ON p.id_penjualan = f.id_penjualan
WHERE p.status_pembayaran <> 'BERHASIL' OR p.status_penjualan = 'BATAL';

-- V4 Konsistensi key 0 dengan kanal dan promo
SELECT 'V4 key 0 sesuai aturan' AS cek,
       SUM(t.kanal_penjualan = 'ONLINE'  AND f.staf_key <> 0)     AS online_berkasir,
       SUM(t.kanal_penjualan = 'OFFLINE' AND f.staf_key = 0)      AS offline_tanpa_kasir,
       SUM(t.kanal_penjualan = 'ONLINE'  AND f.customer_key = 0)  AS online_tanpa_customer,
       SUM(f.promo_key = 0 AND f.harga_normal <> f.harga_satuan)  AS diskon_tanpa_promo,
       SUM(f.promo_key <> 0 AND f.harga_normal = f.harga_satuan)  AS promo_tanpa_diskon,
       IF(SUM((t.kanal_penjualan = 'ONLINE' AND f.staf_key <> 0)
            OR (t.kanal_penjualan = 'OFFLINE' AND f.staf_key = 0)
            OR (t.kanal_penjualan = 'ONLINE' AND f.customer_key = 0)
            OR (f.promo_key = 0 AND f.harga_normal <> f.harga_satuan)
            OR (f.promo_key <> 0 AND f.harga_normal = f.harga_satuan)) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_penjualan f
JOIN umkm_dw.dim_transaksi t ON t.transaksi_key = f.transaksi_key;

-- V5 Kanal dan metode bayar di fakta sama dengan OLTP (lookup dim_transaksi benar)
SELECT 'V5 lookup dim_transaksi' AS cek,
       COUNT(*) AS baris_berbeda,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_penjualan f
JOIN umkm_dw.dim_transaksi t ON t.transaksi_key = f.transaksi_key
JOIN umkm_pakaian.penjualan p ON p.id_penjualan = f.id_penjualan
WHERE p.kanal_penjualan <> t.kanal_penjualan OR p.metode_pembayaran <> t.metode_pembayaran;

-- V6 Produk dan tanggal di fakta sama dengan OLTP (lookup dim_produk dan waktu_key benar)
SELECT 'V6 lookup dim_produk dan waktu_key' AS cek,
       COUNT(*) AS baris_berbeda,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_penjualan f
JOIN umkm_dw.dim_produk dp ON dp.produk_key = f.produk_key
JOIN umkm_dw.dim_waktu w   ON w.waktu_key = f.waktu_key
JOIN umkm_pakaian.detail_penjualan d ON d.id_detail_penjualan = f.id_detail_penjualan
JOIN umkm_pakaian.penjualan p        ON p.id_penjualan = d.id_penjualan
WHERE d.id_varian <> dp.id_varian OR DATE(p.tanggal_penjualan) <> w.tanggal;

-- V7 HPP total DW vs perhitungan langsung dari OLTP
SELECT 'V7 HPP' AS cek,
       (SELECT ROUND(SUM(hpp), 2) FROM umkm_dw.fact_penjualan) AS hpp_dw,
       ROUND(SUM(d.jumlah * h.hpp_unit), 2)                    AS hpp_oltp,
       IF(ABS((SELECT SUM(hpp) FROM umkm_dw.fact_penjualan) - SUM(d.jumlah * h.hpp_unit)) < 1, 'PASS', 'FAIL') AS hasil
FROM umkm_pakaian.detail_penjualan d
JOIN umkm_pakaian.penjualan p ON p.id_penjualan = d.id_penjualan
JOIN (SELECT dp.id_varian, SUM(dp.jumlah * dp.harga_satuan) / SUM(dp.jumlah) AS hpp_unit
      FROM umkm_pakaian.detail_pembelian dp
      JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = dp.id_pembelian
      WHERE pb.status_pembelian = 'DITERIMA'
      GROUP BY dp.id_varian) h ON h.id_varian = d.id_varian
WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL';

-- V8 Pembelian: tanggal fakta = tanggal_diterima, supplier sama dengan OLTP
SELECT 'V8 lookup fact_pembelian' AS cek,
       COUNT(*) AS baris_berbeda,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS hasil
FROM umkm_dw.fact_pembelian f
JOIN umkm_dw.dim_supplier s ON s.supplier_key = f.supplier_key
JOIN umkm_dw.dim_waktu w    ON w.waktu_key = f.waktu_key
JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = f.id_pembelian
WHERE pb.id_supplier <> s.id_supplier OR DATE(pb.tanggal_diterima) <> w.tanggal;

-- V9 Pengeluaran per jenis DW vs OLTP
SELECT 'V9 pengeluaran per jenis' AS cek,
       COUNT(*) AS jenis_berbeda,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS hasil
FROM (SELECT jenis_pengeluaran, SUM(nominal) AS total FROM umkm_pakaian.pengeluaran GROUP BY jenis_pengeluaran) o
LEFT JOIN (SELECT j.jenis_pengeluaran, SUM(f.nominal) AS total
           FROM umkm_dw.fact_pengeluaran f
           JOIN umkm_dw.dim_jenis_pengeluaran j ON j.jenis_pengeluaran_key = f.jenis_pengeluaran_key
           GROUP BY j.jenis_pengeluaran) d ON d.jenis_pengeluaran = o.jenis_pengeluaran
WHERE d.total IS NULL OR ABS(d.total - o.total) > 0.01;

-- V10 Tidak ada duplikat natural key di fakta
SELECT 'V10 duplikat id detail' AS cek,
       (SELECT COUNT(*) - COUNT(DISTINCT id_detail_penjualan) FROM umkm_dw.fact_penjualan)   AS dup_penjualan,
       (SELECT COUNT(*) - COUNT(DISTINCT id_detail_pembelian) FROM umkm_dw.fact_pembelian)   AS dup_pembelian,
       (SELECT COUNT(*) - COUNT(DISTINCT id_pengeluaran)      FROM umkm_dw.fact_pengeluaran) AS dup_pengeluaran,
       IF((SELECT COUNT(*) - COUNT(DISTINCT id_detail_penjualan) FROM umkm_dw.fact_penjualan)
        + (SELECT COUNT(*) - COUNT(DISTINCT id_detail_pembelian) FROM umkm_dw.fact_pembelian)
        + (SELECT COUNT(*) - COUNT(DISTINCT id_pengeluaran)      FROM umkm_dw.fact_pengeluaran) = 0, 'PASS', 'FAIL') AS hasil;

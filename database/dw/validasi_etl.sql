-- ============================================================================
-- VALIDASI ETL: rekonsiliasi OLTP vs DW (sama dengan transformasi 10_validasi_etl.ktr)
-- Jalankan: mysql -uroot umkm_dw < database/dw/validasi_etl.sql
-- Semua baris harus berstatus PASS.
-- ============================================================================
-- Rekonsiliasi OLTP (umkm_pakaian) vs DW (umkm_dw). Setiap baris harus PASS.
SELECT
  NOW() AS dijalankan_pada,
  kode,
  pemeriksaan,
  ROUND(nilai_oltp, 2) AS nilai_oltp,
  ROUND(nilai_dw, 2) AS nilai_dw,
  IF(ABS(COALESCE(nilai_oltp, 0) - COALESCE(nilai_dw, 0)) < 0.005, 'PASS', 'FAIL') AS status
FROM (
  SELECT 'V01' AS kode, 'Baris fakta penjualan = baris detail penjualan valid' AS pemeriksaan,
    (SELECT COUNT(*) FROM umkm_pakaian.detail_penjualan d JOIN umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL' AND p.id_penjualan = d.id_penjualan) AS nilai_oltp,
    (SELECT COUNT(*) FROM umkm_dw.fact_penjualan) AS nilai_dw
  UNION ALL SELECT 'V02', 'Jumlah transaksi (nota) penjualan valid',
    (SELECT COUNT(*) FROM umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'),
    (SELECT COUNT(DISTINCT id_penjualan) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V03', 'Unit terjual',
    (SELECT SUM(d.jumlah) FROM umkm_pakaian.detail_penjualan d JOIN umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL' AND p.id_penjualan = d.id_penjualan),
    (SELECT SUM(jumlah) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V04', 'Omzet: pendapatan bersih DW = nominal bayar OLTP',
    (SELECT SUM(p.nominal_bayar) FROM umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'),
    (SELECT SUM(pendapatan_bersih) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V05', 'Total diskon promo',
    (SELECT SUM(ROUND((d.harga_normal - d.harga_satuan) * d.jumlah, 2)) FROM umkm_pakaian.detail_penjualan d JOIN umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL' AND p.id_penjualan = d.id_penjualan),
    (SELECT SUM(diskon_promo) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V06', 'Total diskon poin',
    (SELECT SUM(p.diskon_poin) FROM umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'),
    (SELECT SUM(diskon_poin) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V07', 'Total poin didapat',
    (SELECT SUM(p.poin_didapat) FROM umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'),
    (SELECT SUM(poin_didapat) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V08', 'Laba kotor = pendapatan bersih - HPP',
    (SELECT SUM(pendapatan_bersih) - SUM(hpp) FROM umkm_dw.fact_penjualan),
    (SELECT SUM(laba_kotor) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V09', 'Baris fakta pembelian = detail pembelian DITERIMA',
    (SELECT COUNT(*) FROM umkm_pakaian.detail_pembelian dp JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = dp.id_pembelian WHERE pb.status_pembelian = 'DITERIMA'),
    (SELECT COUNT(*) FROM umkm_dw.fact_pembelian)
  UNION ALL SELECT 'V10', 'Total pembelian DITERIMA',
    (SELECT SUM(ROUND(dp.jumlah * dp.harga_satuan, 2)) FROM umkm_pakaian.detail_pembelian dp JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = dp.id_pembelian WHERE pb.status_pembelian = 'DITERIMA'),
    (SELECT SUM(total_biaya) FROM umkm_dw.fact_pembelian)
  UNION ALL SELECT 'V11', 'Unit dibeli',
    (SELECT SUM(dp.jumlah) FROM umkm_pakaian.detail_pembelian dp JOIN umkm_pakaian.pembelian_produk pb ON pb.id_pembelian = dp.id_pembelian WHERE pb.status_pembelian = 'DITERIMA'),
    (SELECT SUM(jumlah) FROM umkm_dw.fact_pembelian)
  UNION ALL SELECT 'V12', 'Baris pengeluaran operasional',
    (SELECT COUNT(*) FROM umkm_pakaian.pengeluaran WHERE jenis_pengeluaran <> 'PEMBELIAN_PRODUK'),
    (SELECT COUNT(*) FROM umkm_dw.fact_pengeluaran)
  UNION ALL SELECT 'V13', 'Total biaya operasional',
    (SELECT SUM(nominal) FROM umkm_pakaian.pengeluaran WHERE jenis_pengeluaran <> 'PEMBELIAN_PRODUK'),
    (SELECT SUM(nominal) FROM umkm_dw.fact_pengeluaran)
  UNION ALL SELECT 'V14', 'Tidak ada PEMBELIAN_PRODUK di fakta pengeluaran',
    0,
    (SELECT COUNT(*) FROM umkm_dw.fact_pengeluaran WHERE jenis_pengeluaran = 'PEMBELIAN_PRODUK')
  UNION ALL SELECT 'V15', 'Stok OLTP = unit dibeli DW - unit terjual DW + penyesuaian opname',
    (SELECT SUM(stok) FROM umkm_pakaian.varian_produk),
    (SELECT SUM(jumlah) FROM umkm_dw.fact_pembelian) - (SELECT SUM(jumlah) FROM umkm_dw.fact_penjualan)
      + (SELECT COALESCE(SUM(jumlah), 0) FROM umkm_pakaian.mutasi_stok WHERE jenis = 'PENYESUAIAN')
  UNION ALL SELECT 'V16', 'Tidak ada duplikat baris fakta penjualan',
    0,
    (SELECT COUNT(*) - COUNT(DISTINCT id_detail_penjualan) FROM umkm_dw.fact_penjualan)
  UNION ALL SELECT 'V17', 'Baris dim_produk = jumlah varian OLTP',
    (SELECT COUNT(*) FROM umkm_pakaian.varian_produk),
    (SELECT COUNT(*) FROM umkm_dw.dim_produk)
  UNION ALL SELECT 'V18', 'Baris dim_pelanggan = customer OLTP + 1 (GUEST)',
    (SELECT COUNT(*) + 1 FROM umkm_pakaian.akun WHERE tipe_akun = 'CUSTOMER'),
    (SELECT COUNT(*) FROM umkm_dw.dim_pelanggan)
  UNION ALL SELECT 'V19', 'Baris guest (kunci 0) = detail offline tanpa member',
    (SELECT COUNT(*) FROM umkm_pakaian.detail_penjualan d JOIN umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL' AND p.id_penjualan = d.id_penjualan
       AND p.kanal_penjualan = 'OFFLINE' AND NOT EXISTS (SELECT 1 FROM umkm_pakaian.penjualan_member pm WHERE pm.id_penjualan = p.id_penjualan)),
    (SELECT COUNT(*) FROM umkm_dw.fact_penjualan WHERE pelanggan_key = 0)
  UNION ALL SELECT 'V20', 'Baris promo (kunci promo > 0) = detail valid berpromo',
    (SELECT COUNT(*) FROM umkm_pakaian.detail_penjualan d JOIN umkm_pakaian.penjualan p WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL' AND p.id_penjualan = d.id_penjualan AND d.id_promo IS NOT NULL),
    (SELECT COUNT(*) FROM umkm_dw.fact_penjualan WHERE promo_key > 0)
) cek
ORDER BY kode;

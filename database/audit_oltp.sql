-- ============================================================================
-- AUDIT KUALITAS DATA OLTP (bukti pengujian database operasional, BAB VII)
-- Jalankan pada database OLTP (default: umkm_pakaian):
--   mysql -uroot umkm_pakaian < database/audit_oltp.sql
-- Setiap baris = satu pemeriksaan. Kolom "hasil" harus PASS semua.
-- ============================================================================

SELECT pemeriksaan, nilai, harapan,
       IF(lulus, 'PASS', 'FAIL') AS hasil
FROM (
    -- ---------- Kelengkapan & rentang data ----------
    SELECT 'Jumlah bulan transaksi penjualan' AS pemeriksaan,
           COUNT(DISTINCT DATE_FORMAT(tanggal_penjualan, '%Y-%m')) AS nilai,
           '12' AS harapan,
           COUNT(DISTINCT DATE_FORMAT(tanggal_penjualan, '%Y-%m')) = 12 AS lulus
    FROM penjualan
    UNION ALL
    SELECT 'Rentang tanggal penjualan',
           CONCAT(DATE(MIN(tanggal_penjualan)), ' s.d. ', DATE(MAX(tanggal_penjualan))),
           '2025-12-01 s.d. 2026-11-30',
           DATE(MIN(tanggal_penjualan)) = '2025-12-01' AND DATE(MAX(tanggal_penjualan)) = '2026-11-30'
    FROM penjualan
    UNION ALL
    SELECT 'Jumlah pembelian per status (DITERIMA/DIPESAN/BATAL)',
           CONCAT(SUM(status_pembelian = 'DITERIMA'), '/', SUM(status_pembelian = 'DIPESAN'), '/', SUM(status_pembelian = 'BATAL')),
           'ketiga status ada',
           SUM(status_pembelian = 'DITERIMA') > 0 AND SUM(status_pembelian = 'DIPESAN') > 0 AND SUM(status_pembelian = 'BATAL') > 0
    FROM pembelian_produk
    UNION ALL
    SELECT 'Jenis pengeluaran yang terpakai', COUNT(DISTINCT jenis_pengeluaran), '7', COUNT(DISTINCT jenis_pengeluaran) = 7
    FROM pengeluaran

    -- ---------- Stok ----------
    UNION ALL
    SELECT 'Varian dengan stok minus', COUNT(*), '0', COUNT(*) = 0 FROM varian_produk WHERE stok < 0
    UNION ALL
    SELECT 'Baris mutasi_stok dengan saldo minus', COUNT(*), '0', COUNT(*) = 0 FROM mutasi_stok WHERE stok_sesudah < 0
    UNION ALL
    SELECT 'Stok varian berbeda dari saldo mutasi terakhir', COUNT(*), '0', COUNT(*) = 0
    FROM varian_produk v
    JOIN mutasi_stok m ON m.id_varian = v.id_varian
     AND m.id_mutasi = (SELECT MAX(id_mutasi) FROM mutasi_stok WHERE id_varian = v.id_varian)
    WHERE v.stok <> m.stok_sesudah
    UNION ALL
    SELECT 'Penjualan keluar sebelum stok pertama masuk', COUNT(*), '0', COUNT(*) = 0
    FROM mutasi_stok k
    WHERE k.jenis = 'KELUAR'
      AND k.tanggal < (SELECT MIN(m.tanggal) FROM mutasi_stok m WHERE m.id_varian = k.id_varian AND m.jenis = 'MASUK')

    -- ---------- Konsistensi transaksi ----------
    UNION ALL
    SELECT 'Penjualan tanpa baris detail', COUNT(*), '0', COUNT(*) = 0
    FROM penjualan p WHERE NOT EXISTS (SELECT 1 FROM detail_penjualan d WHERE d.id_penjualan = p.id_penjualan)
    UNION ALL
    SELECT 'nominal_bayar + diskon_poin berbeda dari total detail', COUNT(*), '0', COUNT(*) = 0
    FROM (
        SELECT p.id_penjualan
        FROM penjualan p JOIN detail_penjualan d ON d.id_penjualan = p.id_penjualan
        GROUP BY p.id_penjualan, p.nominal_bayar, p.diskon_poin
        HAVING ABS(p.nominal_bayar + p.diskon_poin - SUM(d.jumlah * d.harga_satuan)) > 1
    ) x
    UNION ALL
    SELECT 'harga_satuan tidak sama dengan harga_normal - diskon', COUNT(*), '0', COUNT(*) = 0
    FROM detail_penjualan WHERE ABS(harga_satuan - harga_normal * (1 - persen_diskon / 100)) > 1
    UNION ALL
    SELECT 'Diskon promo tanpa id_promo', COUNT(*), '0', COUNT(*) = 0
    FROM detail_penjualan WHERE persen_diskon > 0 AND id_promo IS NULL
    UNION ALL
    SELECT 'Promo dipakai di luar periode promo', COUNT(*), '0', COUNT(*) = 0
    FROM detail_penjualan d
    JOIN penjualan p ON p.id_penjualan = d.id_penjualan
    JOIN promo pr ON pr.id_promo = d.id_promo
    WHERE p.tanggal_penjualan < pr.tanggal_mulai OR p.tanggal_penjualan > pr.tanggal_selesai
    UNION ALL
    SELECT 'Promo dipakai pada produk di luar promo_produk', COUNT(*), '0', COUNT(*) = 0
    FROM detail_penjualan d
    JOIN varian_produk v ON v.id_varian = d.id_varian
    WHERE d.id_promo IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM promo_produk pp WHERE pp.id_promo = d.id_promo AND pp.id_produk = v.id_produk)
    UNION ALL
    SELECT 'Transaksi ONLINE oleh non-customer / OFFLINE oleh customer', COUNT(*), '0', COUNT(*) = 0
    FROM penjualan p JOIN akun a ON a.id_akun = p.id_akun
    WHERE (p.kanal_penjualan = 'ONLINE' AND a.tipe_akun <> 'CUSTOMER')
       OR (p.kanal_penjualan = 'OFFLINE' AND a.tipe_akun = 'CUSTOMER')
    UNION ALL
    SELECT 'Customer berbelanja sebelum akun dibuat', COUNT(*), '0', COUNT(*) = 0
    FROM penjualan p
    JOIN akun a ON a.id_akun = p.id_akun
    WHERE a.tipe_akun = 'CUSTOMER' AND p.tanggal_penjualan < a.created_at
    UNION ALL
    SELECT 'Member kasir berbelanja sebelum akun dibuat', COUNT(*), '0', COUNT(*) = 0
    FROM penjualan p
    JOIN penjualan_member pm ON pm.id_penjualan = p.id_penjualan
    JOIN akun a ON a.id_akun = pm.id_akun_customer
    WHERE p.tanggal_penjualan < a.created_at
    UNION ALL
    SELECT 'diskon_poin tidak sama dengan poin_digunakan x 500', COUNT(*), '0', COUNT(*) = 0
    FROM penjualan WHERE diskon_poin <> poin_digunakan * 500

    -- ---------- Pembelian & pengeluaran ----------
    UNION ALL
    SELECT 'Pembelian DITERIMA tanpa tanggal_diterima', COUNT(*), '0', COUNT(*) = 0
    FROM pembelian_produk WHERE status_pembelian = 'DITERIMA' AND tanggal_diterima IS NULL
    UNION ALL
    SELECT 'Pembelian DITERIMA tanpa pengeluaran PEMBELIAN_PRODUK', COUNT(*), '0', COUNT(*) = 0
    FROM pembelian_produk p
    WHERE p.status_pembelian = 'DITERIMA'
      AND NOT EXISTS (SELECT 1 FROM pengeluaran e WHERE e.id_pembelian = p.id_pembelian)
    UNION ALL
    SELECT 'Pengeluaran pembelian untuk pembelian non-DITERIMA', COUNT(*), '0', COUNT(*) = 0
    FROM pengeluaran e JOIN pembelian_produk p ON p.id_pembelian = e.id_pembelian
    WHERE p.status_pembelian <> 'DITERIMA'
    UNION ALL
    SELECT 'Nominal pengeluaran berbeda dari nilai pembelian', COUNT(*), '0', COUNT(*) = 0
    FROM pengeluaran e
    WHERE e.id_pembelian IS NOT NULL
      AND ABS(e.nominal - (SELECT SUM(d.jumlah * d.harga_satuan) FROM detail_pembelian d WHERE d.id_pembelian = e.id_pembelian)) > 1

    -- ---------- Kewajaran bisnis ----------
    UNION ALL
    SELECT 'Rasio pembelian DITERIMA terhadap omzet (%)',
           ROUND(100 * (SELECT SUM(d.jumlah * d.harga_satuan) FROM detail_pembelian d JOIN pembelian_produk p ON p.id_pembelian = d.id_pembelian WHERE p.status_pembelian = 'DITERIMA')
                 / (SELECT SUM(nominal_bayar) FROM penjualan WHERE status_pembayaran = 'BERHASIL' AND status_penjualan <> 'BATAL'), 1),
           '50 - 90',
           (SELECT SUM(d.jumlah * d.harga_satuan) FROM detail_pembelian d JOIN pembelian_produk p ON p.id_pembelian = d.id_pembelian WHERE p.status_pembelian = 'DITERIMA')
             / (SELECT SUM(nominal_bayar) FROM penjualan WHERE status_pembayaran = 'BERHASIL' AND status_penjualan <> 'BATAL') BETWEEN 0.5 AND 0.9
    UNION ALL
    SELECT 'Unit stok akhir / unit terjual (kali)',
           ROUND((SELECT SUM(stok) FROM varian_produk)
                 / (SELECT SUM(d.jumlah) FROM detail_penjualan d JOIN penjualan p ON p.id_penjualan = d.id_penjualan WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL'), 2),
           '<= 0.60',
           (SELECT SUM(stok) FROM varian_produk)
             / (SELECT SUM(d.jumlah) FROM detail_penjualan d JOIN penjualan p ON p.id_penjualan = d.id_penjualan WHERE p.status_pembayaran = 'BERHASIL' AND p.status_penjualan <> 'BATAL') <= 0.6
) hasil_audit;

CREATE DATABASE IF NOT EXISTS umkm_dw CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE umkm_dw;

-- ======================= DIMENSI =======================

CREATE TABLE IF NOT EXISTS dim_waktu (
    waktu_key          INT UNSIGNED PRIMARY KEY,          -- YYYYMMDD
    tanggal            DATE NOT NULL UNIQUE,
    hari               TINYINT UNSIGNED NOT NULL,
    nama_hari          VARCHAR(15) NOT NULL,
    hari_dalam_minggu  TINYINT UNSIGNED NOT NULL,         -- 1 = Senin .. 7 = Minggu
    is_akhir_pekan     TINYINT(1) NOT NULL DEFAULT 0,
    minggu_dalam_tahun TINYINT UNSIGNED NOT NULL,
    bulan              TINYINT UNSIGNED NOT NULL,
    nama_bulan         VARCHAR(15) NOT NULL,
    kuartal            TINYINT UNSIGNED NOT NULL,
    semester           TINYINT UNSIGNED NOT NULL,
    tahun              SMALLINT UNSIGNED NOT NULL
);

CREATE TABLE IF NOT EXISTS dim_produk (
    produk_key     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_varian      VARCHAR(255) NOT NULL UNIQUE,
    ukuran         VARCHAR(10) NOT NULL,
    warna          VARCHAR(50) NOT NULL,
    stok_minimum   INT UNSIGNED NOT NULL DEFAULT 0,
    id_produk      VARCHAR(255) NOT NULL,
    nama_produk    VARCHAR(255) NOT NULL,
    harga_jual     DECIMAL(12,2) NOT NULL,
    status_produk  VARCHAR(10) NOT NULL,
    id_kategori    VARCHAR(255) NOT NULL,
    nama_kategori  VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS dim_customer (
    customer_key    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_akun         VARCHAR(255) NOT NULL UNIQUE,
    nama            VARCHAR(255) NULL,
    username        VARCHAR(255) NULL,
    status_akun     VARCHAR(10) NULL,
    tanggal_daftar  DATE NULL,
    tahun_daftar    SMALLINT UNSIGNED NULL
);

CREATE TABLE IF NOT EXISTS dim_staf (
    staf_key     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_akun      VARCHAR(255) NOT NULL UNIQUE,
    nama         VARCHAR(255) NULL,
    peran        VARCHAR(10) NULL,
    status_akun  VARCHAR(10) NULL
);

CREATE TABLE IF NOT EXISTS dim_promo (
    promo_key        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_promo         VARCHAR(255) NOT NULL UNIQUE,
    nama_promo       VARCHAR(255) NULL,
    persen_diskon    DECIMAL(5,2) NULL,
    tanggal_mulai    DATETIME NULL,
    tanggal_selesai  DATETIME NULL,
    durasi_hari      INT NULL,
    kelompok_diskon  VARCHAR(20) NULL
);

CREATE TABLE IF NOT EXISTS dim_transaksi (
    transaksi_key      INT UNSIGNED PRIMARY KEY,
    kanal_penjualan    VARCHAR(10) NOT NULL,
    metode_pembayaran  VARCHAR(10) NOT NULL,
    UNIQUE (kanal_penjualan, metode_pembayaran)
);

CREATE TABLE IF NOT EXISTS dim_supplier (
    supplier_key   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_supplier    VARCHAR(255) NOT NULL UNIQUE,
    nama_supplier  VARCHAR(255) NULL,
    alamat         TEXT NULL
);

CREATE TABLE IF NOT EXISTS dim_jenis_pengeluaran (
    jenis_pengeluaran_key  INT UNSIGNED PRIMARY KEY,
    jenis_pengeluaran      VARCHAR(30) NOT NULL UNIQUE,
    kelompok               VARCHAR(30) NOT NULL
);

-- Baris khusus "tidak ada / tidak diketahui" (key 0)
SET SESSION sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO');
INSERT IGNORE INTO dim_customer (customer_key, id_akun, nama) VALUES (0, '-', 'GUEST / Non-member');
INSERT IGNORE INTO dim_staf     (staf_key, id_akun, nama, peran) VALUES (0, '-', 'Tidak ada (online)', '-');
INSERT IGNORE INTO dim_promo    (promo_key, id_promo, nama_promo, persen_diskon, kelompok_diskon)
                         VALUES (0, '-', 'Tanpa promo', 0, 'Tanpa promo');

INSERT IGNORE INTO dim_transaksi VALUES
  (1,'ONLINE','QR'), (2,'ONLINE','TUNAI'), (3,'OFFLINE','QR'), (4,'OFFLINE','TUNAI');

INSERT IGNORE INTO dim_jenis_pengeluaran VALUES
  (1,'PEMBELIAN_PRODUK','PEMBELIAN STOK'), (2,'LISTRIK','OPERASIONAL'),
  (3,'INTERNET','OPERASIONAL'), (4,'SEWA','OPERASIONAL'), (5,'ONGKIR','OPERASIONAL'),
  (6,'PERAWATAN','OPERASIONAL'), (7,'LAINNYA','OPERASIONAL');

-- ======================= FAKTA =======================

CREATE TABLE IF NOT EXISTS fact_penjualan (
    penjualan_fact_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waktu_key            INT UNSIGNED NOT NULL,
    produk_key           INT UNSIGNED NOT NULL,
    customer_key         INT UNSIGNED NOT NULL,
    staf_key             INT UNSIGNED NOT NULL,
    promo_key            INT UNSIGNED NOT NULL,
    transaksi_key        INT UNSIGNED NOT NULL,
    id_penjualan         VARCHAR(255) NOT NULL,
    id_detail_penjualan  VARCHAR(255) NOT NULL UNIQUE,
    jumlah               INT NOT NULL,
    harga_normal         DECIMAL(12,2) NOT NULL,
    harga_satuan         DECIMAL(12,2) NOT NULL,
    diskon_promo         DECIMAL(14,2) NOT NULL,
    subtotal             DECIMAL(14,2) NOT NULL,
    diskon_poin          DECIMAL(14,2) NOT NULL,
    pendapatan_bersih    DECIMAL(14,2) NOT NULL,
    hpp                  DECIMAL(14,2) NOT NULL,
    laba_kotor           DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (waktu_key)     REFERENCES dim_waktu(waktu_key),
    FOREIGN KEY (produk_key)    REFERENCES dim_produk(produk_key),
    FOREIGN KEY (customer_key)  REFERENCES dim_customer(customer_key),
    FOREIGN KEY (staf_key)      REFERENCES dim_staf(staf_key),
    FOREIGN KEY (promo_key)     REFERENCES dim_promo(promo_key),
    FOREIGN KEY (transaksi_key) REFERENCES dim_transaksi(transaksi_key),
    INDEX (id_penjualan)
);

CREATE TABLE IF NOT EXISTS fact_pembelian (
    pembelian_fact_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waktu_key            INT UNSIGNED NOT NULL,
    produk_key           INT UNSIGNED NOT NULL,
    supplier_key         INT UNSIGNED NOT NULL,
    staf_key             INT UNSIGNED NOT NULL,
    id_pembelian         VARCHAR(255) NOT NULL,
    id_detail_pembelian  VARCHAR(255) NOT NULL UNIQUE,
    jumlah               INT NOT NULL,
    harga_beli           DECIMAL(12,2) NOT NULL,
    total_biaya          DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (waktu_key)    REFERENCES dim_waktu(waktu_key),
    FOREIGN KEY (produk_key)   REFERENCES dim_produk(produk_key),
    FOREIGN KEY (supplier_key) REFERENCES dim_supplier(supplier_key),
    FOREIGN KEY (staf_key)     REFERENCES dim_staf(staf_key),
    INDEX (id_pembelian)
);

CREATE TABLE IF NOT EXISTS fact_pengeluaran (
    pengeluaran_fact_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waktu_key              INT UNSIGNED NOT NULL,
    jenis_pengeluaran_key  INT UNSIGNED NOT NULL,
    staf_key               INT UNSIGNED NOT NULL,
    id_pengeluaran         VARCHAR(255) NOT NULL UNIQUE,
    nominal                DECIMAL(14,2) NOT NULL,
    FOREIGN KEY (waktu_key)             REFERENCES dim_waktu(waktu_key),
    FOREIGN KEY (jenis_pengeluaran_key) REFERENCES dim_jenis_pengeluaran(jenis_pengeluaran_key),
    FOREIGN KEY (staf_key)              REFERENCES dim_staf(staf_key)
);


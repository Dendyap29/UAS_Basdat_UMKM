# Star Schema DW FORMA (`umkm_dw`)

Galaxy schema: 3 fakta berbagi dimensi conformed `dim_waktu` (dipakai 3 fakta) dan `dim_produk` (dipakai 2 fakta).
DDL: `database/migrations/dw/2026_11_25_000001_create_star_schema_umkm_dw.php` (setara `database/dw/umkm_dw_schema.sql`).

## Diagram

```mermaid
erDiagram
    dim_waktu ||--o{ fact_penjualan : waktu_key
    dim_produk ||--o{ fact_penjualan : produk_key
    dim_pelanggan ||--o{ fact_penjualan : pelanggan_key
    dim_promo ||--o{ fact_penjualan : promo_key
    dim_transaksi ||--o{ fact_penjualan : transaksi_key

    dim_waktu ||--o{ fact_pembelian : waktu_key
    dim_produk ||--o{ fact_pembelian : produk_key
    dim_supplier ||--o{ fact_pembelian : supplier_key

    dim_waktu ||--o{ fact_pengeluaran : waktu_key

    fact_penjualan {
        bigint penjualan_fact_id PK
        int waktu_key FK
        int produk_key FK
        int pelanggan_key FK
        int promo_key FK
        int transaksi_key FK
        varchar id_penjualan "degenerate"
        varchar id_detail_penjualan "unik, kunci sumber"
        int jumlah
        decimal nilai_kotor
        decimal diskon_promo
        decimal diskon_poin
        decimal pendapatan_bersih
        decimal hpp
        decimal laba_kotor
        decimal poin_didapat
        decimal poin_ditukar
    }
    fact_pembelian {
        bigint pembelian_fact_id PK
        int waktu_key FK "tanggal diterima"
        int produk_key FK
        int supplier_key FK
        varchar id_pembelian "degenerate"
        varchar id_detail_pembelian "unik"
        int jumlah
        decimal total_biaya
    }
    fact_pengeluaran {
        bigint pengeluaran_fact_id PK
        int waktu_key FK
        varchar id_pengeluaran "degenerate, unik"
        varchar jenis_pengeluaran "degenerate"
        decimal nominal
    }
    dim_waktu {
        int waktu_key PK "YYYYMMDD"
        date tanggal
        tinyint hari_dalam_minggu
        varchar nama_hari
        varchar jenis_hari
        tinyint bulan
        varchar nama_bulan
        tinyint kuartal
        smallint tahun
    }
    dim_produk {
        int produk_key PK
        varchar id_varian "unik"
        varchar ukuran
        varchar warna
        varchar id_produk
        varchar nama_produk
        varchar nama_kategori
    }
    dim_pelanggan {
        int pelanggan_key PK "0 = GUEST"
        varchar id_akun "unik"
        varchar nama
        varchar tipe_pelanggan
        date tanggal_daftar
    }
    dim_promo {
        int promo_key PK "0 = Tanpa promo"
        varchar id_promo "unik"
        varchar nama_promo
        decimal persen_diskon
        varchar kelompok_diskon
        date tanggal_mulai
        date tanggal_selesai
        smallint durasi_hari
    }
    dim_transaksi {
        int transaksi_key PK "junk"
        varchar kanal_penjualan
        varchar metode_pembayaran
    }
    dim_supplier {
        int supplier_key PK
        varchar id_supplier "unik"
        varchar nama_supplier
        varchar kota
    }
```

`etl_validasi` (log hasil rekonsiliasi setiap eksekusi job) adalah tabel metadata, bukan bagian star schema.

## Bus matrix

| Proses / Fakta | Waktu | Produk | Pelanggan | Promo | Transaksi | Supplier | Degenerate |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| Penjualan / `fact_penjualan` | ● | ● | ● | ● | ● | | id_penjualan |
| Pembelian / `fact_pembelian` | ● | ● | | | | ● | id_pembelian |
| Pengeluaran / `fact_pengeluaran` | ● | | | | | | id_pengeluaran, jenis_pengeluaran |

## Aturan integritas

- Semua FK fakta `NOT NULL` dengan foreign key ke dimensi. Baris yang kuncinya tidak ditemukan saat ETL ditolak database, sehingga job gagal (tidak dilewati diam-diam).
- Konsep "tidak ada" memakai baris kunci 0 (GUEST, Tanpa promo), bukan NULL.
- `id_detail_*` dan `id_pengeluaran` unik, mencegah baris ganda.
- Surrogate key dipakai untuk produk, pelanggan, promo, transaksi, dan supplier supaya DW tidak bergantung pada format ID OLTP. Waktu memakai smart key `YYYYMMDD` yang stabil dan mudah dibaca.

## Drill-across

Fakta tidak pernah di-JOIN langsung satu sama lain. Setiap fakta diagregasi sendiri pada dimensi bersama (bulan, kategori, varian), lalu hasil agregasinya digabung. Contoh SQL ada di `database/dw/olap_queries.sql` nomor 7 dan 8; di Mondrian dilakukan oleh virtual cube `Keuangan`.

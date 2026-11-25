# Laporan Validasi End-to-End

Dijalankan 25 November 2026 di laptop pengembang: Laragon MySQL 8.0.30, PHP 8.2.12, Java 21, Pentaho DI 9.4, Mondrian (Schema Workbench).
Semua hasil di bawah berasal dari eksekusi nyata, bukan perkiraan.

## 1. OLTP: audit kualitas data

Perintah: `mysql -uroot umkm_pakaian < database/audit_oltp.sql`. Hasil: **24/24 PASS**.

| Pemeriksaan (ringkas) | Hasil |
| :--- | :--- |
| 12 bulan transaksi, rentang 2025-12-01 s.d. 2026-11-30 | PASS |
| Pembelian per status DITERIMA/DIPESAN/BATAL = 562/6/40 | PASS |
| Stok varian dan saldo mutasi tidak minus; stok = saldo mutasi terakhir | PASS |
| Tidak ada penjualan sebelum stok pertama masuk | PASS |
| Setiap nota punya detail; nominal_bayar + diskon_poin = total detail | PASS |
| harga_satuan = harga_normal − diskon; diskon promo selalu punya id_promo | PASS |
| Promo hanya dipakai dalam periodenya dan pada produk di promo_produk | PASS |
| Kanal sesuai peran (ONLINE oleh customer, OFFLINE oleh kasir) | PASS |
| Customer/member tidak berbelanja sebelum akun dibuat | PASS |
| diskon_poin = poin_digunakan × 500 | PASS |
| Pembelian DITERIMA punya tanggal_diterima dan tepat satu pengeluaran PEMBELIAN_PRODUK dengan nominal yang sama | PASS |
| Rasio pembelian/omzet 76,6% (wajar 50–90%); stok akhir/unit terjual 0,28 | PASS |

## 2. ETL: rekonsiliasi OLTP vs DW

Dijalankan otomatis oleh transformasi `10_validasi_etl.ktr` di akhir job, disimpan di `umkm_dw.etl_validasi`. Hasil: **20/20 PASS**.

| ID | Pemeriksaan | OLTP | DW | Status |
| :--- | :--- | ---: | ---: | :--- |
| V01 | Baris fakta penjualan = detail penjualan valid | 6.743 | 6.743 | PASS |
| V02 | Jumlah nota valid | 3.363 | 3.363 | PASS |
| V03 | Unit terjual | 10.174 | 10.174 | PASS |
| V04 | Omzet = Σ nominal_bayar | 2.324.863.730 | 2.324.863.730 | PASS |
| V05 | Diskon promo | 34.835.770 | 34.835.770 | PASS |
| V06 | Diskon poin | 4.956.500 | 4.956.500 | PASS |
| V07 | Poin didapat | 186.672 | 186.672 | PASS |
| V08 | Laba kotor = pendapatan − HPP | 929.642.529,76 | 929.642.529,76 | PASS |
| V09 | Baris fakta pembelian = detail DITERIMA | 2.492 | 2.492 | PASS |
| V10 | Total pembelian DITERIMA | 1.781.750.600 | 1.781.750.600 | PASS |
| V11 | Unit dibeli | 12.990 | 12.990 | PASS |
| V12 | Baris pengeluaran operasional | 66 | 66 | PASS |
| V13 | Total biaya operasional | 101.561.923 | 101.561.923 | PASS |
| V14 | Tidak ada PEMBELIAN_PRODUK di fakta pengeluaran | 0 | 0 | PASS |
| V15 | Stok OLTP = unit dibeli − unit terjual + opname | 2.803 | 2.803 | PASS |
| V16 | Tidak ada duplikat baris fakta penjualan | 0 | 0 | PASS |
| V17 | dim_produk = jumlah varian | 319 | 319 | PASS |
| V18 | dim_pelanggan = customer + GUEST | 161 | 161 | PASS |
| V19 | Baris guest (kunci 0) = detail offline tanpa member | 1.290 | 1.290 | PASS |
| V20 | Baris berpromo = detail valid dengan id_promo | 606 | 606 | PASS |

Hitungan step Kitchen (extract = load, reject = 0):
- `fact_penjualan` I=6.743 → O=6.743
- `fact_pembelian` I=2.492 → O=2.492
- `fact_pengeluaran` I=66 → O=66
- Lookup produk/pelanggan/promo/transaksi memuat 319/161/11/3 baris kunci

### Uji tambahan ETL

| Uji | Cara | Hasil |
| :--- | :--- | :--- |
| Pengaman Abort | Hapus 1 baris `fact_pengeluaran`, jalankan `10_validasi_etl.ktr` | V12 FAIL (66 vs 65); step Abort menghentikan transformasi dengan pesan error. **PASS** |
| Idempoten / reload | Jalankan job 2× berturut-turut | Baris dan total identik (6.743 baris, omzet Rp2.324.863.730), 20/20 PASS. **PASS** |
| Exit code Kitchen | 3 eksekusi penuh | exit 0, semua entri job `result=[true]` |

## 3. OLAP: MDX vs SQL

Perintah: `run_mdx.ps1`. Seluruh 28 query MDX berjalan. **11/11 PASS** pada perbandingan sel MDX dengan SQL langsung. Rinciannya ada di [OLAP_MDX_DOCUMENTATION.md](OLAP_MDX_DOCUMENTATION.md) bagian 3.

## 4. Dashboard vs query

| Validasi | Dashboard | Pembanding | Status |
| :--- | :--- | :--- | :--- |
| D-1 Omzet seluruh periode | Rp2.324.863.730 | V04 / C1 | PASS |
| D-2 Laba kotor | Rp929.642.530 | V08 / C2 (dibulatkan) | PASS |
| D-3 Laba operasional | Rp828.080.607 | C7 (dibulatkan) | PASS |
| D-4 Arus kas | Rp441.551.207 | C6 | PASS |
| D-5 Unit terjual Jaket Q4 2026 (filter + drill V3) | 69+64+62+52+52 = 299 | C9 | PASS |
| D-6 Varian diam ≥ 90 hari | 13 varian, 104 unit | `olap_queries.sql` no. 12: 13 baris | PASS |
| D-7 Header validasi | "validasi ETL 20/20 PASS" | `etl_validasi` | PASS |
| D-8 Konsol browser | tidak ada error JavaScript | – | PASS |

## 5. Tes otomatis aplikasi

`php artisan test`: **20 passed (101 assertions)**.
- `FinalSystemSmokeTest`: 12 tes OLTP (RBAC, pembelian → stok, poin, pembayaran gagal, pembatalan, member, promo, proteksi hapus, tampilan admin).
- `DataWarehouseDashboardTest`: 6 tes. Mencakup skema tepat 3 fakta + 6 dimensi, tidak ada ETL Laravel, pesan DW kosong, KPI dan drill-across, filter periode, serta drill-down produk.
- `ExampleTest`: 2 tes.

## 6. Yang belum diuji otomatis

- Membuka `.kjb`/`.ktr` lewat antarmuka grafis Spoon dan skema lewat GUI Schema Workbench. File sudah dimuat dan dijalankan oleh mesin yang sama (Kitchen/Pan dan library Mondrian), tetapi tampilan GUI perlu dicek manual saat gladi demo.
- Tampilan dashboard di layar proyektor/resolusi kecil.

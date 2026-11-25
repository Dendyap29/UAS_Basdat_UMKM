# Dokumentasi OLAP Mondrian dan MDX

Skema: `database/dw/umkm_dw_olap_schema.xml`. Query: `database/dw/mondrian/olap_mdx_queries.mdx` (28 blok).
Runner: `database/dw/mondrian/MdxRunner.java` + `run_mdx.ps1`, memakai library Mondrian dari Schema Workbench dan driver MySQL dari Pentaho.

## 1. Struktur cube

| Objek | Jenis | Isi |
| :--- | :--- | :--- |
| `Waktu` | shared dimension (TimeDimension) | hierarki `Kalender`: Tahun → Kuartal → Bulan → Tanggal |
| `Hari` | shared dimension (dari `dim_waktu`) | Jenis Hari → Nama Hari. Dipisah dari Waktu karena hierarki TimeDimension Mondrian hanya menerima level tahun/kuartal/bulan/minggu/hari |
| `Produk` | shared dimension | hierarki `Katalog`: Kategori → Produk → Varian; hierarki `Ukuran` dan `Warna` untuk pivot |
| `Penjualan` | cube (`fact_penjualan`) | dimensi Waktu, Hari, Produk, Pelanggan (Tipe → Pelanggan), Promo (Kelompok diskon → Promo), Transaksi (Kanal → Metode bayar). Measure: Unit Terjual, Nilai Kotor, Diskon Promo, Diskon Poin, Omzet, HPP, Laba Kotor, Poin Didapat, Poin Ditukar, Jumlah Transaksi (distinct-count). Calculated: Margin Kotor, Rata-rata Transaksi |
| `Pembelian` | cube (`fact_pembelian`) | dimensi Waktu, Produk, Supplier (Kota → Supplier). Measure: Unit Dibeli, Total Pembelian, Jumlah Pembelian. Calculated: Harga Beli Rata-rata (tertimbang) |
| `Pengeluaran` | cube (`fact_pengeluaran`) | dimensi Waktu, Jenis Pengeluaran (degenerate). Measure: Biaya Operasional, Jumlah Catatan Biaya |
| `Keuangan` | **virtual cube** | gabungan 3 cube pada Waktu dan Produk. Calculated: Laba Operasional, Arus Kas, Selisih Unit Dibeli dan Terjual |

Virtual cube adalah cara Mondrian melakukan drill-across: setiap cube dihitung sendiri, lalu nilainya disandingkan pada anggota dimensi bersama. Cube `Pengeluaran` memakai `ignoreUnrelatedDimensions="true"` karena tidak punya dimensi Produk.

## 2. Operasi OLAP yang didemonstrasikan

| Operasi | Blok MDX | Contoh | Di dashboard |
| :--- | :--- | :--- | :--- |
| Roll-up | D1, V1 | Bulan → Kuartal → Tahun dengan `Hierarchize` | tombol Bulan/Kuartal di V1 |
| Drill-down | D2, D3, D4, V8a | Kuartal 4 → bulan; Semua → Kategori → Produk (Jaket) → Varian | klik kategori/produk di V3 |
| Slice | D5, V5, C8 | `WHERE [Transaksi].[ONLINE]` | filter tahun/kuartal |
| Dice | D6, V6, C9 | Q1 2026 × {Kaos, Kemeja} × kanal | filter periode + kategori (drill V3) |
| Pivot | D7, D8, V7 | Ukuran × warna, lalu sumbunya ditukar | V4 (ukuran × warna), V7 (hari × kanal) |
| Drill-across | V2, V3, C6, C7, C11 | Omzet vs pembelian vs biaya per bulan; unit dibeli vs terjual per kategori | V2, V3 |

## 3. Hasil yang sudah dijalankan

Perintah:

```powershell
powershell -ExecutionPolicy Bypass -File database\dw\mondrian\run_mdx.ps1
```

Hasil pada 25 Nov 2026: seluruh 28 query berjalan. Blok `C1`–`C11` membandingkan sel MDX dengan SQL langsung ke `umkm_dw`: **11 PASS, 0 FAIL**.

| Cek | MDX | SQL | Status |
| :--- | ---: | ---: | :--- |
| C1 Omzet | 2.324.863.730 | 2.324.863.730 | PASS |
| C2 Laba kotor | 929.642.529,76 | 929.642.529,76 | PASS |
| C3 Jumlah transaksi | 3.363 | 3.363 | PASS |
| C4 Total pembelian | 1.781.750.600 | 1.781.750.600 | PASS |
| C5 Biaya operasional | 101.561.923 | 101.561.923 | PASS |
| C6 Arus kas (virtual cube) | 441.551.207 | 441.551.207 | PASS |
| C7 Laba operasional (virtual cube) | 828.080.606,76 | 828.080.606,76 | PASS |
| C8 Slice omzet ONLINE | 1.018.300.780 | 1.018.300.780 | PASS |
| C9 Dice unit Jaket Q4 2026 | 299 | 299 | PASS |
| C10 Omzet hari Sabtu | 418.108.840 | 418.108.840 | PASS |
| C11 Unit dibeli Kaos (drill-across) | 2.799 | 2.799 | PASS |

Contoh keluaran V2 (drill-across per bulan, dipotong):

```
             Omzet          Total Pembelian   Biaya Operasional   Laba Operasional   Arus Kas
Desember     242,075,100    521,312,400       8,362,112           88,257,582         -287,599,412
Januari      177,437,750    120,623,600       7,999,634           64,045,568          48,814,516
...
November     235,212,850    148,039,100       8,538,099           83,909,835          78,635,651
```

## 4. Membuka di Schema Workbench (demo)

1. Jalankan `C:\schema-workbench\workbench.bat`.
2. *Tools → Connection*: driver MySQL, URL `jdbc:mysql://127.0.0.1:3306/umkm_dw`, user `root`. Driver diambil dari `C:\pentaho\data-integration\lib\mysql-connector-*.jar`; salin ke `C:\schema-workbench\drivers` jika belum ada.
3. *File → Open* → `database/dw/umkm_dw_olap_schema.xml`.
4. *File → New → MDX Query*, lalu salin salah satu blok dari `olap_mdx_queries.mdx`, misalnya D4 untuk drill-down atau D7 untuk pivot.

Nama hierarki memakai format Mondrian 3: `[Waktu.Kalender]`, `[Produk.Katalog]`, `[Produk.Ukuran]`, `[Produk.Warna]`.

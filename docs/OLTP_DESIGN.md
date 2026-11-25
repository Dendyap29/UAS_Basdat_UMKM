# Desain OLTP FORMA (`umkm_pakaian`)

ERD dan PDM rinci: [ERD_PDM.md](ERD_PDM.md) bagian 1. Migrasi: `database/migrations/`. Seeder: `database/seeders/PrototypeSeeder.php`.

## 1. Keputusan: skema OLTP dipertahankan

OLTP diaudit ulang pada revisi 25 Nov 2026 dan **tidak diubah**:
- 14 tabel sudah memisahkan master dan transaksi.
- Setiap relasi punya foreign key.
- Status transaksi jelas.
- Data lolos 24/24 pemeriksaan kualitas ([VALIDATION_REPORT.md](VALIDATION_REPORT.md) bagian 1).

Mengubah skema mendekati presentasi akan memaksa perubahan di aplikasi, seeder, ETL, dan tes tanpa menambah kemampuan analisis.

## 2. Tabel dan proses bisnis

| Proses | Tabel | Catatan |
| :--- | :--- | :--- |
| Akun & peran | `akun` | `tipe_akun`: CUSTOMER, KASIR, ADMIN |
| Katalog | `kategori_produk` → `produk` → `varian_produk` | varian = SKU (ukuran + warna), menyimpan stok saat ini |
| Penjualan online/toko | `penjualan` (header) → `detail_penjualan` | status bayar MENUNGGU/BERHASIL/GAGAL; status pesanan BARU…SELESAI/BATAL; poin per nota |
| Member di toko | `penjualan_member` | menghubungkan nota offline ke akun customer; tanpa baris = guest |
| Promo | `promo`, `promo_produk` | promo per produk dengan periode; harga dan persen diskon disalin ke `detail_penjualan` saat transaksi |
| Pembelian stok | `pembelian_produk` → `detail_pembelian` | status DIPESAN/DITERIMA/BATAL; `tanggal_diterima` |
| Buku kas keluar | `pengeluaran` | jenis operasional + `PEMBELIAN_PRODUK` (dibuat otomatis saat pembelian diterima) |
| Kartu stok | `mutasi_stok` | MASUK (pembelian), KELUAR (penjualan), PENYESUAIAN (opname), dengan `stok_sesudah` |

## 3. Aturan bisnis yang dijaga aplikasi dan dibuktikan audit

- Stok tidak pernah minus; stok varian = saldo mutasi terakhir.
- Penjualan valid = BERHASIL dan tidak BATAL. Pembayaran gagal tidak mengurangi stok maupun memberi poin.
- `nominal_bayar + diskon_poin = Σ subtotal detail`; `diskon_poin = poin_digunakan × 500`.
- Promo hanya berlaku dalam periodenya dan pada produk yang terdaftar.
- Pembelian DITERIMA menambah stok sekali dan membuat tepat satu pengeluaran `PEMBELIAN_PRODUK` bernilai sama.

## 4. Catatan desain yang perlu diketahui saat ETL

| Hal | Dampak ke DW |
| :--- | :--- |
| `penjualan.id_akun` berarti customer (ONLINE) atau kasir (OFFLINE) | ETL menurunkan pelanggan dari kanal: ONLINE → id_akun, OFFLINE → `penjualan_member` |
| `pengeluaran` juga memuat `PEMBELIAN_PRODUK` | Tidak dimuat ke `fact_pengeluaran` agar tidak dobel dengan `fact_pembelian` |
| HPP tidak disimpan di OLTP | ETL menghitung rata-rata tertimbang harga beli DITERIMA per varian |

## 5. Data simulasi

Seeder deterministik (seed tetap). Isinya:
- Periode tetap 1 Des 2025 – 30 Nov 2026.
- 32 produk, 319 varian, 6 kategori, 6 supplier, 160 customer, 4 kasir, 2 admin, 10 promo.
- Restock otomatis berbasis kebutuhan.

Data ini **simulasi, bukan transaksi riil**. Pola yang muncul adalah hasil aturan seeder, termasuk musim ramai November–Desember, akhir pekan lebih ramai, ukuran XXL lebih lambat, dan promo dengan diskon berbeda.

Backup database Laragon sebelum reset 25 Nov 2026 tersimpan lokal di `storage/app/backup_sebelum_rombak/` (tidak di-commit).

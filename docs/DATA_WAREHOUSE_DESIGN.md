# Desain Data Warehouse FORMA

Alasan pemilihan fakta dan dimensi: [FINAL_DESIGN_DECISION.md](FINAL_DESIGN_DECISION.md). ERD: [STAR_SCHEMA.md](STAR_SCHEMA.md).
Angka berasal dari data simulasi seeder (1 Des 2025 – 30 Nov 2026).

## 1. Tujuan dan pengguna

**Pengguna:** pemilik/Admin FORMA. Kasir dan Customer cukup memakai OLTP.

**Peran OLTP vs DW:**
- OLTP (dashboard Admin, menu Laporan) menjawab "apa yang terjadi sekarang": stok menipis hari ini, transaksi terbaru.
- DW menjawab "bagaimana polanya, apa penyebabnya, dan apa yang perlu dilakukan".

| Tujuan | Keputusan pemilik |
| :--- | :--- |
| T1. Kesehatan usaha | Kapan menahan atau menambah pembelian; apakah biaya terkendali |
| T2. Produk dan stok | Restock apa, varian mana yang dihentikan, ukuran/warna apa yang dibeli |
| T3. Strategi penjualan | Promo mana yang layak diulang, cara menambah member, jadwal staf |

## 2. Masalah bisnis dan pertanyaan

Kolom "Temuan" adalah hasil hitung dari data, bukan asumsi. Kolom "Keputusan" adalah rekomendasi, bukan klaim sebab-akibat.

| Masalah | Pertanyaan bisnis | Fakta | Dimensi | Measure | Temuan (data sekarang) | Keputusan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| P1. Kas jauh di bawah laba | Kenapa arus kas lebih kecil dari laba? | penjualan, pembelian, pengeluaran | waktu | omzet, HPP, pembelian, biaya | Laba operasional Rp828,1 jt vs arus kas Rp441,6 jt; selisih Rp386,5 jt = pembelian − HPP (stok yang belum terjual). Desember arus kas −Rp287,6 jt karena pembelian stok awal Rp521,3 jt | Kendalikan nilai pembelian per bulan |
| P2. Stok lambat | Varian mana yang masih bersisa tetapi tidak laku? | penjualan, pembelian | produk, waktu | unit terjual, unit dibeli, tanggal jual terakhir | 13 varian (104 unit) tidak terjual ≥ 90 hari; hampir semuanya ukuran XXL | Hentikan restock XXL tertentu, bundling/cuci gudang |
| P3. Promo menggerus margin | Promo mana yang layak diulang? | penjualan | promo | omzet, diskon promo, laba | Margin tanpa promo 40,9%; promo 15,3–34,1%; "Twin-Date 11.11" (30%) margin 15,3%; "Ramadhan" (25%) diskon Rp12,5 jt untuk omzet Rp37,4 jt | Batasi persen diskon; nilai promo dari laba |
| P4. Banyak pembeli toko bukan member | Seberapa besar porsi guest? | penjualan | pelanggan, transaksi | transaksi, omzet | 642 dari 1.902 transaksi toko (33,8%) guest | Kasir menawarkan pendaftaran member |
| P5. Program poin jarang dipakai | Berapa poin yang ditukar? | penjualan | pelanggan | poin didapat/ditukar | Ditukar 9.913 dari 186.672 poin (5,3%) | Kampanye tukar poin ke member bersaldo besar |
| P6. Pola hari | Kapan toko ramai? | penjualan | waktu (hari), transaksi | jumlah transaksi | Minggu ±12 transaksi/hari, hari kerja ±8 | Tambah kasir/stok pajang di akhir pekan |
| P7. Musim | Bulan apa puncak dan sepi? | penjualan | waktu | omzet | Puncak Desember Rp242,1 jt dan November Rp235,2 jt; terendah Juni Rp164,3 jt | Waktu pembelian stok |
| P8. Uang keluar | Ke supplier mana dan pos biaya apa? | pembelian, pengeluaran | supplier, jenis | total pembelian, nominal | 6 supplier, porsi Rp249–345 jt (relatif merata); biaya operasional Rp101,6 jt | Dasar negosiasi dan kontrol biaya |

## 3. Fakta

### 3.1 `fact_penjualan` (transaction fact)

| Aspek | Isi |
| :--- | :--- |
| Proses bisnis | Penjualan online (web, QR) dan toko (POS, QR/tunai) |
| Grain | 1 varian dalam 1 nota yang `status_pembayaran = BERHASIL` dan `status_penjualan ≠ BATAL` |
| Alasan grain | Produk, ukuran/warna, dan promo melekat per baris barang; 423 nota mencampur barang promo dan non-promo |
| PK | `penjualan_fact_id` (surrogate); kunci sumber unik `id_detail_penjualan` |
| FK | `waktu_key`, `produk_key`, `pelanggan_key`, `promo_key`, `transaksi_key` |
| Degenerate | `id_penjualan` (nomor nota, untuk COUNT DISTINCT) |
| Sumber | `detail_penjualan`, `penjualan`, `penjualan_member`; HPP dari `detail_pembelian` + `pembelian_produk` |
| Baris | 6.743 |

| Measure | Rumus | Sifat |
| :--- | :--- | :--- |
| `jumlah` | `detail_penjualan.jumlah` | additive |
| `nilai_kotor` | harga_normal × jumlah | additive |
| `diskon_promo` | (harga_normal − harga_satuan) × jumlah | additive |
| `diskon_poin` | diskon_poin nota × subtotal baris ÷ subtotal nota (sisa pembulatan ke baris terakhir) | additive |
| `pendapatan_bersih` | nilai_kotor − diskon_promo − diskon_poin | additive; Σ per nota = `nominal_bayar` |
| `hpp` | jumlah × HPP rata-rata tertimbang varian (Σ jumlah × harga beli ÷ Σ jumlah, pembelian DITERIMA) | additive |
| `laba_kotor` | pendapatan_bersih − hpp | additive |
| `poin_didapat`, `poin_ditukar` | poin nota dialokasikan seperti diskon poin | additive |

Tidak disimpan: harga satuan (non-additive, bisa dihitung = nilai ÷ jumlah), margin, rata-rata transaksi.

### 3.2 `fact_pembelian` (transaction fact)

| Aspek | Isi |
| :--- | :--- |
| Proses bisnis | Pembelian stok dari supplier |
| Grain | 1 varian dalam 1 pembelian `DITERIMA` |
| Alasan | DIPESAN belum menjadi stok dan belum dibayar; BATAL tidak terjadi. Tanggal = `tanggal_diterima` (saat stok masuk dan uang keluar) |
| PK / kunci sumber | `pembelian_fact_id` / `id_detail_pembelian` |
| FK | `waktu_key`, `produk_key`, `supplier_key` |
| Degenerate | `id_pembelian` |
| Measure | `jumlah` (additive), `total_biaya` = jumlah × harga beli (additive) |
| Baris | 2.492 |

### 3.3 `fact_pengeluaran` (transaction fact)

| Aspek | Isi |
| :--- | :--- |
| Proses bisnis | Pembayaran biaya operasional |
| Grain | 1 catatan pengeluaran operasional (LISTRIK, INTERNET, SEWA, ONGKIR, PERAWATAN, LAINNYA) |
| Aturan | `PEMBELIAN_PRODUK` tidak dimuat; nilainya identik dengan `fact_pembelian` |
| FK | `waktu_key` |
| Degenerate | `id_pengeluaran`, `jenis_pengeluaran` |
| Measure | `nominal` (additive) |
| Baris | 66 |

## 4. Dimensi

| Dimensi | Kolom | Sumber | Kunci khusus | Baris |
| :--- | :--- | :--- | :--- | :--- |
| `dim_waktu` | waktu_key (YYYYMMDD), tanggal, hari_dalam_minggu, nama_hari, jenis_hari, bulan, nama_bulan, kuartal, tahun | dibangkitkan ETL dari rentang tanggal transaksi | – | 365 |
| `dim_produk` | produk_key, id_varian, ukuran, warna, id_produk, nama_produk, nama_kategori | varian_produk + produk + kategori_produk | – | 319 |
| `dim_pelanggan` | pelanggan_key, id_akun, nama, tipe_pelanggan, tanggal_daftar | akun (CUSTOMER) | 0 = Guest (non-member) | 161 |
| `dim_promo` | promo_key, id_promo, nama_promo, persen_diskon, kelompok_diskon, tanggal_mulai, tanggal_selesai, durasi_hari | promo | 0 = Tanpa promo | 11 |
| `dim_transaksi` (junk) | transaksi_key, kanal_penjualan, metode_pembayaran | kombinasi aktual dari penjualan valid | – | 3 |
| `dim_supplier` | supplier_key, id_supplier, nama_supplier, kota | supplier (kota diambil dari alamat) | – | 6 |

Semua dimensi memakai SCD tipe 1 (ditimpa saat full load). SCD tipe 2 tidak diperlukan karena nilai historis yang penting (harga, diskon, HPP) sudah tersimpan di fakta.

Tidak dimuat dari OLTP:
- **Data pribadi** (email, no_telp, alamat pelanggan): tidak dipakai analisis.
- **Data operasional yang berubah** (harga_jual, stok_minimum, status_produk): tidak menjadi sudut pandang analisis.
- **Kasir**: lihat FINAL_DESIGN_DECISION bagian 4.

## 5. KPI

| KPI | Definisi / rumus | Fakta | Nilai (seluruh periode) | Catatan |
| :--- | :--- | :--- | :--- | :--- |
| Omzet | Σ pendapatan_bersih | penjualan | Rp2.324.863.730 | Hanya transaksi valid; = Σ nominal_bayar OLTP |
| Laba kotor | Σ laba_kotor | penjualan | Rp929.642.530 | |
| Margin kotor | laba kotor ÷ omzet | penjualan | 40,0% | Rasio dari total, bukan rata-rata per baris |
| Laba operasional | laba kotor − Σ nominal | penjualan + pengeluaran | Rp828.080.607 | Belum termasuk pajak/penyusutan |
| Arus kas operasional | omzet − Σ total_biaya − Σ nominal | 3 fakta | Rp441.551.207 | Pembayaran tunai di muka (FORMA tidak punya kredit) |
| Stok bertambah | Σ pembelian − Σ HPP | pembelian + penjualan | Rp386.529.400 | Identitas: laba operasional − arus kas |
| Jumlah transaksi | COUNT DISTINCT id_penjualan | penjualan | 3.363 | Grain per baris, jadi wajib DISTINCT |
| Rata-rata transaksi | omzet ÷ jumlah transaksi | penjualan | Rp691.307 | Non-additive |
| Unit terjual / dibeli | Σ jumlah | penjualan / pembelian | 10.174 / 12.990 | Dibandingkan lewat drill-across |

Laba ≠ arus kas. Pembelian ≠ biaya operasional. Omzet ≠ seluruh uang yang pernah dibayarkan customer (pesanan gagal tidak termasuk).

## 6. Keterbatasan

- Data simulasi seeder, bukan transaksi riil.
- HPP memakai rata-rata tertimbang seluruh periode, bukan FIFO atau rata-rata bergerak. Harga beli varian bergeser ±12% sepanjang tahun.
- Pesanan online gagal (85 nota, Rp63,3 jt) tidak dianalisis di DW (batas 3 fakta); tersedia di OLTP.
- Stock opname (3 baris, −13 unit) tidak masuk fakta mana pun; selisihnya ditunjukkan di validasi V15.
- Full load, belum incremental.

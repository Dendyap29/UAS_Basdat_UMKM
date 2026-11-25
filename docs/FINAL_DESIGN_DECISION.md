# Keputusan Desain Final DW FORMA

Tanggal keputusan: 25 November 2026. Target demo: 16 Desember 2026.
Semua angka di dokumen ini berasal dari data simulasi seeder (1 Des 2025 – 30 Nov 2026), dihitung langsung dari database `umkm_pakaian` dan `umkm_dw`.

## 1. Ringkasan

| Komponen | Keputusan |
| :--- | :--- |
| Tema | Analitik penjualan, persediaan, dan arus kas UMKM fashion FORMA (online + toko) |
| Fakta | **Tepat 3**: `fact_penjualan`, `fact_pembelian`, `fact_pengeluaran` |
| Dimensi | **6**: `dim_waktu`, `dim_produk`, `dim_pelanggan`, `dim_promo`, `dim_transaksi` (junk), `dim_supplier` |
| Degenerate dimension | `id_penjualan`, `id_pembelian`, `id_pengeluaran`, `jenis_pengeluaran` |
| Skema | Galaxy (fact constellation); dimensi conformed: waktu (3 fakta) dan produk (2 fakta) |
| ETL | Satu jalur: job Pentaho, full load, validasi otomatis di akhir job |
| OLAP | Mondrian: 3 cube + virtual cube `Keuangan` |
| OLTP | Skema dipertahankan (lolos 24/24 audit kualitas data) |

## 2. Masalah desain lama yang diperbaiki

| Masalah | Bukti | Dampak | Perbaikan |
| :--- | :--- | :--- | :--- |
| Pembelian stok tercatat di dua fakta | `fact_pengeluaran` memuat 562 baris `PEMBELIAN_PRODUK` = Rp1.781.750.600, sama persis dengan `fact_pembelian` | Total uang keluar terhitung dua kali | `PEMBELIAN_PRODUK` tidak dimuat ke `fact_pengeluaran` (validasi V14) |
| Dua jalur ETL dengan perilaku berbeda | ETL Artisan membuang fakta basi; Pentaho memakai Insert/Update tanpa pembersihan | Hasil DW bisa berbeda tergantung jalur | ETL Artisan dihapus; Pentaho satu-satunya ETL, full load |
| Measure Mondrian salah | "Harga Beli Rata-rata" memakai `aggregator="avg"` per baris | Rata-rata tidak tertimbang jumlah | Calculated member Total Pembelian ÷ Unit Dibeli |
| Harga satuan disimpan sebagai measure | `harga_normal`, `harga_satuan`, `harga_beli` di fakta | Nilai non-additive bisa dijumlahkan | Fakta hanya menyimpan nilai yang sudah dikalikan jumlah |
| Dimensi tanpa pertanyaan bisnis | `dim_staf` (3 kasir dengan beban 629/632/641 transaksi), `staf_key` di pembelian/pengeluaran | Kompleksitas tanpa insight | Dihapus |
| Tidak ada MDX di repo, dimensi tidak shared | Dimensi Waktu/Produk ditulis ulang di setiap cube | Klaim operasi OLAP tidak terbukti | Shared dimension, virtual cube, 28 query MDX teruji |
| DDL DW dan baris statis dibuat Laravel | `dim_transaksi`/`dim_jenis_pengeluaran` diisi migrasi | Job Pentaho tidak bisa berjalan sendiri | Migrasi hanya membuat struktur; semua isi (termasuk kunci 0) dimuat Pentaho |
| Salinan `.ktr` ganda | Folder `dimensi/` dan `fact/` di root berbeda isi dengan `database/dw/pentaho/` | Tidak jelas file resmi | Dipindah ke backup lokal; hanya `database/dw/pentaho/` yang resmi |

## 3. Pemilihan tepat 3 fakta

| Kandidat | Proses bisnis | Ketersediaan data | Grain valid? | Nilai analitik | Kompleksitas | Keputusan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `fact_penjualan` | Menjual (online & toko) | `penjualan` + `detail_penjualan`, 6.743 baris valid | Ya: 1 varian per nota | Tinggi: omzet, HPP, laba, produk, promo, member, kanal, hari | Sedang | **Dipilih** |
| `fact_pembelian` | Membeli stok dari supplier | `pembelian_produk` + `detail_pembelian`, 2.492 baris DITERIMA | Ya: 1 varian per pembelian diterima | Tinggi: kas keluar untuk stok, beli vs terjual, supplier | Rendah | **Dipilih** |
| `fact_pengeluaran` | Membayar biaya operasional | `pengeluaran`, 66 baris operasional | Ya: 1 catatan biaya | Sedang: laba operasional dan arus kas tidak bisa dihitung tanpa ini | Rendah | **Dipilih** |
| `fact_stok_bulanan` | Posisi persediaan | `mutasi_stok` lengkap (2.492 masuk, 6.743 keluar, 3 opname) | Ya | Tinggi, tetapi **dapat diturunkan** dari dua fakta lain | Tinggi (snapshot, semi-additive) | **Tidak dipilih** |
| Pesanan/pembayaran gagal | Checkout online yang gagal | 85 nota GAGAL/BATAL (Rp63,3 jt) | Ya, tetapi proses berbeda dari penjualan | Sedang | Butuh fakta keempat agar tidak tercampur dengan omzet | **Tidak dipilih** |

**Kenapa stok tidak perlu fakta sendiri.** Semua stok di FORMA masuk lewat pembelian DITERIMA (mutasi MASUK 2.492 = baris detail pembelian) dan keluar lewat penjualan valid (mutasi KELUAR 6.743 = baris fakta penjualan). Satu-satunya perubahan lain adalah stock opname 3 baris (−13 unit). Hasil yang terbukti di validasi V15:

```
Σ unit dibeli (12.990) − Σ unit terjual (10.174) + penyesuaian opname (−13) = stok OLTP (2.803)
```

Jadi posisi dan perputaran stok per varian bisa dijawab dengan drill-across `fact_pembelian` dan `fact_penjualan` lewat `dim_produk`, tanpa mengarang snapshot.

**Kenapa pesanan gagal tidak dimuat.** Memasukkan pesanan gagal ke `fact_penjualan` mencampur penjualan aktual dengan potensi penjualan, sehingga setiap query omzet wajib menyaring status. Dengan batas 3 fakta, analisis pesanan gagal tetap di OLTP (menu Penjualan, filter status) dan dicatat sebagai pengembangan (fakta keempat `fact_pesanan_online` dengan status sebagai junk dimension).

**Kenapa pembelian dan pengeluaran tidak digabung.** Grain dan dimensinya berbeda: pembelian per varian dengan supplier, pengeluaran per nota tanpa produk. Kalau digabung, `produk_key` dan `supplier_key` kosong pada baris biaya, dan risiko double counting kembali muncul.

## 4. Evaluasi dimensi

Kardinalitas diukur dari data aktual.

| Kandidat | Dipakai fakta | Nilai bisnis | Atribut berguna | Kardinalitas | Hierarki | Keputusan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Waktu | 3 fakta | Tren, musim, pola hari, sumbu drill-across | tanggal, hari, jenis hari, bulan, kuartal, tahun | 365 | Tahun → Kuartal → Bulan → Tanggal; Jenis hari → Hari | **Dipertahankan** (conformed) |
| Produk | penjualan, pembelian | Produk laku/lambat, beli vs terjual, ukuran/warna | varian, ukuran, warna, produk, kategori | 319 | Kategori → Produk → Varian | **Dipertahankan** (conformed); 3 tabel OLTP diratakan |
| Pelanggan | penjualan | Member vs guest, poin per member | id, nama, tipe (MEMBER/GUEST), tanggal daftar | 161 (160 member + GUEST) | Tipe → Pelanggan | **Dipertahankan** |
| Promo | penjualan | Margin dan biaya per promo | nama, persen, kelompok diskon, periode, durasi | 11 (10 promo + Tanpa promo) | Kelompok diskon → Promo | **Dipertahankan** |
| Kanal + metode bayar | penjualan | Online vs toko | kanal, metode | **3 kombinasi aktual** | Kanal → Metode | **Digabung** jadi junk `dim_transaksi` |
| Supplier | pembelian | Konsentrasi pembelian per pemasok | nama, kota | 6 | Kota → Supplier | **Dipertahankan** |
| Kasir/staf | penjualan | Tidak ada keputusan: 3 kasir dengan beban merata | – | 7 | – | **Dihapus** |
| Jenis pengeluaran | pengeluaran | Komposisi biaya | 1 atribut, 6 nilai | 6 | – | **Degenerate** (kolom di fakta) |
| Status transaksi | – | Hanya transaksi valid yang dimuat | – | 1 nilai | – | **Tidak dimuat** |

### Alasan tiap dimensi berdiri sendiri atau digabung

- **Promo tidak masuk junk `dim_transaksi`.**
  - Promo melekat per baris barang: 423 nota mencampur barang promo dan non-promo.
  - Promo punya atribut yang dianalisis sendiri: persen, periode, dan durasi untuk menghitung omzet per hari aktif.
  - Kalau digabung, kombinasi junk naik dari 3 menjadi 30 baris (diukur dari fakta), dan setiap promo baru menambah kombinasi.
- **Promo tidak masuk `dim_waktu`.** Ada 4 pasang promo yang periodenya tumpang tindih (PRM001 dengan PRM002, PRM009, PRM010; PRM002 dengan PRM010), dan masing-masing hanya berlaku untuk produk tertentu.
- **Pelanggan tidak masuk junk.** Jumlahnya 160 dan terus bertambah; digabung dengan kanal/metode sudah menghasilkan 473 kombinasi aktual. Flag member/guest disimpan sebagai atribut `tipe_pelanggan`, bukan dua kali.
- **Supplier tidak masuk `dim_produk`.** Saat ini setiap varian kebetulan hanya dibeli dari 1 supplier, tetapi skema OLTP menaruh supplier di header pembelian sehingga satu varian boleh dibeli dari supplier lain.
- **Kanal dan metode digabung.** Keduanya flag kecil tanpa atribut lain, dipakai satu fakta, dan saling berkaitan (online selalu QR). Ini sesuai definisi junk dimension Kimball: hanya kombinasi yang benar-benar terjadi yang disimpan.

## 5. Bus matrix

| Fakta \ Dimensi | Waktu | Produk | Pelanggan | Promo | Transaksi | Supplier |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| fact_penjualan | ● | ● | ● | ● | ● | |
| fact_pembelian | ● (tgl diterima) | ● | | | | ● |
| fact_pengeluaran | ● | | | | | |

## 6. Keputusan yang sengaja tidak diambil

| Alternatif | Alasan ditolak |
| :--- | :--- |
| Fakta stok bulanan (snapshot) | Posisi stok sudah dapat diturunkan dan dibuktikan (V15); batas 3 fakta |
| Pesanan gagal di `fact_penjualan` | Mencampur penjualan aktual dengan potensi penjualan |
| `dim_akun` role-playing (pelanggan + kasir) | Atribut berbeda; kasir tidak dianalisis |
| Snowflake (kategori jadi tabel sendiri) | Join tambahan tanpa manfaat; kategori hanya 1 atribut |
| Incremental load | Data ribuan baris; full load selesai ±2 detik dan selalu identik dengan OLTP |
| ETL ganda Pentaho + Artisan | Dua sumber kebenaran |

## 7. Rujukan

- Kimball, R., & Ross, M. (2013). *The Data Warehouse Toolkit* (3rd ed.). Wiley. Bab 3 (Retail Sales: grain, degenerate dimension, promotion dimension), bab 4 (Inventory: periodic snapshot, conformed dimension, bus matrix), bab 6 (junk dimension).
- Kimball Group, *Dimensional Modeling Techniques*: https://www.kimballgroup.com/data-warehouse-business-intelligence-resources/kimball-techniques/dimensional-modeling-techniques/ (Junk Dimensions, Drilling Across, Conformed Dimension, Additive/Semi-Additive/Non-Additive Facts).
- Pentaho Mondrian: https://github.com/pentaho/mondrian

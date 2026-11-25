# Perkiraan Pertanyaan Dosen dan Jawaban

Jawaban berdasarkan implementasi FORMA yang sebenarnya.

**1. Kenapa perlu Data Warehouse, tidak query OLTP saja?**
OLTP dirancang untuk mencatat transaksi. Datanya tersebar di 14 tabel, statusnya bisa berubah, dan HPP tidak tersimpan. DW menyimpan data final yang sudah dihitung (HPP, laba, poin per baris) dalam bentuk bintang, sehingga query analisis sederhana dan tidak membebani kasir maupun checkout.

**2. Apa beda OLTP dan OLAP di proyek ini?**
OLTP (`umkm_pakaian`) dipakai Customer, Kasir, dan Admin untuk bertransaksi. OLAP (Mondrian di atas `umkm_dw`) dipakai pemilik untuk melihat pola: tren, perbandingan, drill-down.

**3. Kenapa tepat 3 fakta ini?**
Ada 3 proses bisnis dengan grain berbeda: menjual (per varian per nota), membeli stok (per varian per pembelian diterima), dan membayar biaya (per catatan). Posisi stok tidak perlu fakta sendiri karena bisa diturunkan: unit dibeli − unit terjual + opname = stok OLTP (validasi V15).

**4. Kenapa pembelian tidak digabung ke pengeluaran?**
Grain dan dimensinya berbeda (pembelian punya produk dan supplier). Di OLTP, setiap pembelian diterima juga dicatat sebagai pengeluaran `PEMBELIAN_PRODUK` dengan nilai sama. Jika keduanya dimuat, uang keluar terhitung dua kali. Karena itu baris tersebut tidak dimuat (V14).

**5. Apa grain `fact_penjualan` dan kenapa?**
Satu varian dalam satu nota yang berhasil dan tidak batal. Produk, ukuran/warna, dan promo melekat per baris, dan 423 nota mencampur barang promo dan non-promo.

**6. Bagaimana menghitung jumlah transaksi kalau grain-nya per barang?**
`COUNT(DISTINCT id_penjualan)`. `id_penjualan` adalah degenerate dimension.

**7. Kenapa ada 6 dimensi?**
Setiap kandidat diuji: dipakai fakta apa, atributnya dianalisis atau tidak, dan berapa kardinalitasnya.
- Waktu dan produk: conformed.
- Pelanggan: 160 dan bertambah.
- Promo: atributnya dianalisis.
- Kanal + metode: flag kecil, digabung menjadi junk berisi 3 kombinasi.
- Supplier: konteks pembelian.

Kasir dihapus karena 3 kasir punya beban hampir sama (629/632/641 transaksi), jadi tidak ada keputusan yang bisa diambil dari situ.

**8. Kenapa promo tidak digabung ke junk dimension?**
- Promo punya atribut yang dianalisis (persen, periode, durasi untuk omzet per hari).
- Kombinasi junk akan naik dari 3 menjadi 30.
- Promo tidak bisa jadi atribut waktu karena ada 4 pasang promo yang periodenya tumpang tindih.

**9. Apa itu conformed dimension di sini?**
`dim_waktu` dan `dim_produk`: satu tabel dipakai beberapa fakta. Di Mondrian dibuat sebagai shared dimension, sehingga omzet, pembelian, dan biaya bisa disandingkan di virtual cube `Keuangan`.

**10. Kenapa memakai surrogate key?**
DW tidak bergantung pada format ID OLTP, bisa memakai baris 0 untuk "tidak ada" (GUEST, Tanpa promo) sehingga FK tidak pernah NULL, dan join integer lebih ringan. `dim_waktu` memakai smart key YYYYMMDD yang stabil.

**11. Additive vs semi-additive vs non-additive?**
- Additive: unit, omzet, HPP, laba. Boleh dijumlah di semua dimensi.
- Semi-additive: posisi stok. Boleh dijumlah antarproduk, tetapi tidak antarbulan; karena itu tidak disimpan sebagai fakta.
- Non-additive: margin, harga satuan, rata-rata transaksi. Tidak disimpan, dihitung dari total.

**12. Bagaimana ETL dilakukan?**
Job Pentaho:
- reset (full load),
- 6 transformasi dimensi,
- 3 transformasi fakta dengan Database lookup ke surrogate key,
- transformasi validasi.

Durasinya ±2 detik.

**13. Bagaimana mencegah data ganda?**
- Full load mengosongkan tabel dulu.
- `id_detail_*` bersifat UNIQUE.
- Job yang dijalankan 2× berturut-turut menghasilkan angka identik.
- Validasi V16 memastikan tidak ada duplikat.

**14. Bagaimana memastikan total DW sama dengan OLTP?**
Transformasi `10_validasi_etl` membandingkan 20 angka (baris, unit, omzet, diskon, HPP/laba, pembelian, biaya, stok). Jika ada FAIL, step Abort menghentikan job. Ini sudah diuji dengan sengaja merusak satu baris.

**15. Jelaskan slice, dice, roll-up, drill-down, pivot pada FORMA.**
- **Slice:** omzet khusus kanal ONLINE, atau filter tahun.
- **Dice:** Q1 2026 × {Kaos, Kemeja} × kanal.
- **Roll-up:** bulan → kuartal → tahun (tombol di V1).
- **Drill-down:** kategori → produk → varian (klik di V3).
- **Pivot:** ukuran × warna lalu sumbunya ditukar (V4).
- **Drill-across:** omzet vs pembelian vs biaya per bulan (V2).

**16. Kenapa drill-across tidak memakai JOIN antar fakta?**
Grain-nya berbeda. JOIN langsung akan menggandakan baris. Setiap fakta diagregasi sendiri per bulan/produk, baru hasilnya digabung. Virtual cube Mondrian melakukan hal yang sama.

**17. Kenapa visual-visual ini yang dipilih?**
Setiap visual menjawab satu pertanyaan pemilik dan memicu keputusan. Kinerja kasir, metode bayar, dan lead time supplier tidak ditampilkan karena datanya datar atau tidak memicu keputusan.

**18. Apa insight paling penting?**
Laba operasional Rp828 jt tetapi arus kas Rp442 jt. Selisih Rp386 jt adalah pembelian yang belum terjual (pembelian − HPP). 13 varian, sebagian besar XXL, diam lebih dari 90 hari.

**19. Kenapa rekomendasinya begitu?**
Uang tertahan di stok yang lambat. Membatasi restock varian lambat langsung memperbaiki arus kas tanpa mengurangi penjualan varian yang laku. Rekomendasi promo berbasis margin, bukan klaim bahwa promo menaikkan penjualan.

**20. Apa keterbatasan dataset dan desain?**
- Data simulasi seeder.
- HPP rata-rata tertimbang seluruh periode (bukan FIFO).
- Pesanan online gagal tidak dianalisis di DW.
- Stock opname tidak masuk fakta.
- Full load.

**21. Jika bisnis berkembang?**
- Tambah fakta keempat `fact_pesanan_online` (status sebagai junk dimension) untuk analisis gagal bayar.
- Incremental load berbasis `updated_at`.
- Dimensi kasir jika jumlah toko bertambah.

Grain atomik dan dimensi conformed membuat penambahan ini tidak membongkar yang sudah ada.

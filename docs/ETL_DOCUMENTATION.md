# Dokumentasi ETL (Pentaho Data Integration)

Satu-satunya jalur ETL: `database/dw/pentaho/job_etl_umkm_dw.kjb`. Laravel tidak menjalankan ETL.
Diuji dengan Pentaho Data Integration 9.4 (Kitchen) terhadap MySQL 8.0.30 Laragon.

## 1. Alur

```
OLTP umkm_pakaian ──(koneksi OLTP_UMKM)──► Pentaho ──(koneksi DW_UMKM)──► DW umkm_dw
                                                     │
              00 reset → 01–06 dimensi → 07–09 fakta → 10 validasi (Abort jika FAIL)
```

Koneksi (`127.0.0.1:3306`, user `root`, tanpa password) didefinisikan di setiap `.ktr` dan di job. Jika lingkungan berbeda, ubah di Spoon (*View → Database connections*) atau sunting tag `<connection>`.

## 2. Langkah job

| Urutan | Entri | Input | Proses | Output | Validasi |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 00 | `00_reset_dw` (SQL) | – | TRUNCATE 3 fakta, 6 dimensi, `etl_validasi`; buat baris kunci 0 (GUEST, Tanpa promo) | DW kosong + 2 baris kunci 0 | – |
| 01 | `01_dim_waktu.ktr` | tanggal penjualan, pembelian diterima, pengeluaran | Recursive CTE: kalender harian dari awal bulan pertama s.d. akhir bulan terakhir; nama hari/bulan Indonesia | `dim_waktu` (365) | semua tanggal fakta ada (FK) |
| 02 | `02_dim_produk.ktr` | varian_produk, produk, kategori_produk | Join 3 tabel, rapikan teks | `dim_produk` (319) | V17 |
| 03 | `03_dim_pelanggan.ktr` | akun CUSTOMER | Tipe = MEMBER, tanggal daftar | `dim_pelanggan` (160 + GUEST) | V18 |
| 04 | `04_dim_promo.ktr` | promo | Kelompok diskon (≤10%, 11–20%, >20%), durasi hari | `dim_promo` (10 + Tanpa promo) | – |
| 05 | `05_dim_transaksi.ktr` | penjualan valid | DISTINCT kanal × metode (junk, hanya kombinasi yang terjadi) | `dim_transaksi` (3) | – |
| 06 | `06_dim_supplier.ktr` | supplier | Kota = bagian alamat setelah koma terakhir | `dim_supplier` (6) | – |
| 07 | `07_fact_penjualan.ktr` | detail_penjualan, penjualan, penjualan_member, pembelian DITERIMA | Filter status valid; hitung measure; alokasi poin per baris; HPP rata-rata tertimbang; 4 Database lookup (produk, pelanggan, promo, transaksi) | `fact_penjualan` (6.743) | V01–V08, V16, V19, V20 |
| 08 | `08_fact_pembelian.ktr` | detail_pembelian, pembelian_produk | Hanya DITERIMA, tanggal = tanggal diterima; 2 lookup | `fact_pembelian` (2.492) | V09–V11 |
| 09 | `09_fact_pengeluaran.ktr` | pengeluaran | Buang `PEMBELIAN_PRODUK` | `fact_pengeluaran` (66) | V12–V14 |
| 10 | `10_validasi_etl.ktr` | OLTP + DW | 20 pemeriksaan rekonsiliasi → `etl_validasi`; cabang kedua mengambil baris FAIL → step **Abort** | log PASS/FAIL | job gagal jika ada FAIL |

## 3. Aturan transformasi

| Hal | Aturan |
| :--- | :--- |
| Penjualan valid | `status_pembayaran = 'BERHASIL'` dan `status_penjualan <> 'BATAL'` |
| Pelanggan | ONLINE → `penjualan.id_akun`; OFFLINE → `penjualan_member.id_akun_customer`, jika tidak ada → `'-'` (kunci 0 GUEST) |
| Promo | `detail_penjualan.id_promo`, jika kosong → `'-'` (kunci 0) |
| Alokasi poin | `ROUND(nilai_nota × subtotal_baris ÷ subtotal_nota, 2)`; baris terakhir menerima sisa pembulatan sehingga Σ per nota tepat (window function `ROW_NUMBER`/`SUM OVER`) |
| HPP | `jumlah × Σ(jumlah × harga_satuan) ÷ Σ jumlah` per varian dari pembelian DITERIMA |
| Kota supplier | `TRIM(SUBSTRING_INDEX(alamat, ',', -1))` |

## 4. Kualitas data dan penanganan kesalahan

- **Kunci tidak ditemukan:** Database lookup menghasilkan NULL, lalu Table output ditolak oleh constraint `NOT NULL`/FK. Transformasi gagal dan job berhenti. Tidak ada baris yang hilang diam-diam.
- **Duplikat:** kunci sumber `id_detail_*` bersifat UNIQUE. Full load mengosongkan tabel dulu, jadi menjalankan ulang job tidak pernah menggandakan data (diuji dua kali berturut-turut, hasilnya identik).
- **Rekonsiliasi:** transformasi 10 membandingkan 20 angka OLTP vs DW. Jika ada FAIL, step Abort menghentikan job dan log menyebut pemeriksaan yang gagal. Ini sudah diuji dengan sengaja menghapus satu baris `fact_pengeluaran`: V12 menjadi FAIL (66 vs 65) dan transformasi dihentikan.
- **Log:** keluaran Kitchen ada di konsol/berkas log; hasil validasi tersimpan di tabel `umkm_dw.etl_validasi` dan ditampilkan di header dashboard.

## 5. Kenapa full load

- Data ribuan baris; job selesai ±2 detik.
- DW selalu identik dengan OLTP, termasuk transaksi yang dibatalkan setelah sempat dimuat. Ini masalah di desain lama.
- Tidak perlu logika deteksi perubahan.

Incremental load (berdasarkan `updated_at`) dicatat sebagai pengembangan jika data membesar.

## 6. Menjalankan

**Spoon (untuk demo):** buka `Spoon.bat` → *File → Open* → `database/dw/pentaho/job_etl_umkm_dw.kjb` → *Run*.

**Headless (Kitchen), PowerShell:**

```powershell
Push-Location C:\pentaho\data-integration; $env:SPOON_CONSOLE = "1"; .\Spoon.bat -main org.pentaho.di.kitchen.Kitchen -initialDir "<folder proyek>\database\dw\pentaho\" "/file:<folder proyek>\database\dw\pentaho\job_etl_umkm_dw.kjb" /level:Basic; Pop-Location
```

`Kitchen.bat` langsung bisa gagal di sebagian instalasi karena path relatif, sehingga perintah di atas memanggil kelas Kitchen lewat `Spoon.bat`.

**Cek hasil:**

```powershell
mysql -uroot umkm_dw -e "SELECT kode, pemeriksaan, nilai_oltp, nilai_dw, status FROM etl_validasi"
```

## 7. Pemeliharaan file Pentaho

File `.ktr`/`.kjb` boleh disunting langsung di Spoon. SQL setiap step tertulis di dalam file tersebut; `database/dw/validasi_etl.sql` berisi query yang sama dengan transformasi 10 untuk dijalankan manual.

<?php

namespace Tests\Feature;

use App\Models\Akun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tes skema DW (3 fakta, 6 dimensi) dan dashboard analitik.
 *
 * ETL resmi adalah job Pentaho (tidak bisa dijalankan dari PHPUnit), jadi DW diisi dengan
 * data kecil yang nilainya diketahui. Rekonsiliasi ETL terhadap OLTP diuji oleh
 * transformasi 10_validasi_etl.ktr / database/dw/validasi_etl.sql.
 */
class DataWarehouseDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Akun $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.dw', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('dw');
        Artisan::call('migrate', ['--database' => 'dw', '--path' => 'database/migrations/dw', '--force' => true]);

        DB::table('akun')->insert([
            'id_akun' => 'ADM900', 'nama' => 'Admin Uji', 'username' => 'admin_uji',
            'password_hash' => bcrypt('rahasia-uji'), 'email' => 'admin.uji@forma.test',
            'no_telp' => '080000000000', 'tipe_akun' => 'ADMIN', 'status_akun' => 'AKTIF',
        ]);
        $this->admin = Akun::findOrFail('ADM900');
    }

    public function test_dw_schema_has_exactly_three_facts_and_six_dimensions(): void
    {
        $dw = Schema::connection('dw');

        foreach (['fact_penjualan', 'fact_pembelian', 'fact_pengeluaran'] as $fact) {
            $this->assertTrue($dw->hasTable($fact), "{$fact} harus ada");
        }
        foreach (['dim_waktu', 'dim_produk', 'dim_pelanggan', 'dim_promo', 'dim_transaksi', 'dim_supplier'] as $dim) {
            $this->assertTrue($dw->hasTable($dim), "{$dim} harus ada");
        }
        foreach (['fact_stok_bulanan', 'dim_staf', 'dim_customer', 'dim_jenis_pengeluaran'] as $removed) {
            $this->assertFalse($dw->hasTable($removed), "{$removed} sudah dihapus dari desain final");
        }

        $tables = collect(DB::connection('dw')->select("SELECT name FROM sqlite_master WHERE type = 'table'"))->pluck('name');
        $this->assertSame(3, $tables->filter(fn ($t) => str_starts_with($t, 'fact_'))->count());
        $this->assertSame(6, $tables->filter(fn ($t) => str_starts_with($t, 'dim_'))->count());

        // Grain & measure kunci
        $this->assertTrue($dw->hasColumns('fact_penjualan', ['id_penjualan', 'id_detail_penjualan', 'pendapatan_bersih', 'hpp', 'laba_kotor', 'poin_didapat']));
        $this->assertFalse($dw->hasColumn('fact_penjualan', 'harga_satuan'), 'harga satuan non-additive tidak disimpan');
        $this->assertFalse($dw->hasColumn('fact_pembelian', 'staf_key'));
        $this->assertTrue($dw->hasColumn('fact_pengeluaran', 'jenis_pengeluaran'), 'jenis pengeluaran = degenerate dimension');
    }

    public function test_etl_is_not_run_from_laravel(): void
    {
        $this->assertArrayNotHasKey('dw:sync', Artisan::all());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.analitik.sync'));
    }

    public function test_dashboard_explains_missing_dw_data(): void
    {
        $this->actingAs($this->admin)->get(route('admin.analitik'))
            ->assertOk()
            ->assertSee('Data Warehouse belum siap')
            ->assertSee('job_etl_umkm_dw.kjb');
    }

    public function test_dashboard_kpi_and_drill_across_match_fact_tables(): void
    {
        $this->loadFixture();

        // Omzet 1.200.000 + 450.000 = 1.650.000; HPP 900.000; laba 750.000
        // Pembelian 1.000.000; operasional 100.000
        $this->actingAs($this->admin)->get(route('admin.analitik'))
            ->assertOk()
            ->assertSee('Rp1.650.000')                 // omzet
            ->assertSee('2 transaksi')                 // COUNT DISTINCT nota, bukan jumlah baris (3)
            ->assertSee('Rp750.000')                   // laba kotor
            ->assertSee('Rp650.000')                   // laba operasional = 750.000 - 100.000
            ->assertSee('Rp550.000')                   // arus kas = 1.650.000 - 1.000.000 - 100.000
            ->assertSee('Selisih Rp100.000')           // laba op - arus kas = pembelian - HPP
            ->assertSee('validasi ETL <b>1/1 PASS</b>', false);
    }

    public function test_period_filter_slices_all_facts(): void
    {
        $this->loadFixture();

        // Januari & Februari 2026 sama-sama Q1, jadi seluruh omzet tetap masuk
        $this->actingAs($this->admin)->get(route('admin.analitik', ['tahun' => 2026, 'kuartal' => 1]))
            ->assertOk()
            ->assertSee('Rp1.650.000');               // Januari & Februari sama-sama Q1

        $this->actingAs($this->admin)->get(route('admin.analitik', ['tahun' => 2025]))
            ->assertOk()
            ->assertSee('Rp0');
    }

    public function test_product_drill_down_compares_bought_and_sold_units(): void
    {
        $this->loadFixture();

        // Level kategori: Kaos terjual 3 unit (Rp900.000), dibeli 10 unit
        $this->actingAs($this->admin)->get(route('admin.analitik'))
            ->assertSeeInOrder(['Kaos', '3', 'Rp900.000']);

        // Drill ke kategori Kaos -> produk
        $this->actingAs($this->admin)->get(route('admin.analitik', ['kat' => 'Kaos']))
            ->assertSee('Produk dalam kategori Kaos')
            ->assertSee('Kaos Basic');

        // Drill ke produk -> varian
        $this->actingAs($this->admin)->get(route('admin.analitik', ['kat' => 'Kaos', 'produk' => 'PRD1']))
            ->assertSee('M / hitam');
    }

    private function loadFixture(): void
    {
        $dw = DB::connection('dw');

        $dw->table('dim_waktu')->insert([
            ['waktu_key' => 20260105, 'tanggal' => '2026-01-05', 'hari_dalam_minggu' => 1, 'nama_hari' => 'Senin', 'jenis_hari' => 'Hari kerja', 'bulan' => 1, 'nama_bulan' => 'Januari', 'kuartal' => 1, 'tahun' => 2026],
            ['waktu_key' => 20260110, 'tanggal' => '2026-01-10', 'hari_dalam_minggu' => 6, 'nama_hari' => 'Sabtu', 'jenis_hari' => 'Akhir pekan', 'bulan' => 1, 'nama_bulan' => 'Januari', 'kuartal' => 1, 'tahun' => 2026],
            ['waktu_key' => 20260210, 'tanggal' => '2026-02-10', 'hari_dalam_minggu' => 2, 'nama_hari' => 'Selasa', 'jenis_hari' => 'Hari kerja', 'bulan' => 2, 'nama_bulan' => 'Februari', 'kuartal' => 1, 'tahun' => 2026],
        ]);
        $dw->table('dim_produk')->insert([
            ['produk_key' => 1, 'id_varian' => 'VAR1', 'ukuran' => 'M', 'warna' => 'hitam', 'id_produk' => 'PRD1', 'nama_produk' => 'Kaos Basic', 'nama_kategori' => 'Kaos'],
            ['produk_key' => 2, 'id_varian' => 'VAR2', 'ukuran' => 'L', 'warna' => 'olive', 'id_produk' => 'PRD2', 'nama_produk' => 'Jaket Kerja', 'nama_kategori' => 'Jaket'],
        ]);
        $dw->table('dim_pelanggan')->insert([
            ['pelanggan_key' => 0, 'id_akun' => '-', 'nama' => 'Guest (non-member)', 'tipe_pelanggan' => 'GUEST', 'tanggal_daftar' => null],
            ['pelanggan_key' => 1, 'id_akun' => 'CUST1', 'nama' => 'Member Uji', 'tipe_pelanggan' => 'MEMBER', 'tanggal_daftar' => '2026-01-01'],
        ]);
        $dw->table('dim_promo')->insert([
            ['promo_key' => 0, 'id_promo' => '-', 'nama_promo' => 'Tanpa promo', 'persen_diskon' => 0, 'kelompok_diskon' => 'Tanpa promo', 'tanggal_mulai' => null, 'tanggal_selesai' => null, 'durasi_hari' => 0],
            ['promo_key' => 1, 'id_promo' => 'PRM1', 'nama_promo' => 'Promo Uji', 'persen_diskon' => 10, 'kelompok_diskon' => '<=10%', 'tanggal_mulai' => '2026-02-01', 'tanggal_selesai' => '2026-02-28', 'durasi_hari' => 28],
        ]);
        $dw->table('dim_transaksi')->insert([
            ['transaksi_key' => 1, 'kanal_penjualan' => 'ONLINE', 'metode_pembayaran' => 'QR'],
            ['transaksi_key' => 2, 'kanal_penjualan' => 'OFFLINE', 'metode_pembayaran' => 'TUNAI'],
        ]);
        $dw->table('dim_supplier')->insert([
            ['supplier_key' => 1, 'id_supplier' => 'SUP1', 'nama_supplier' => 'Supplier Uji', 'kota' => 'Bandung'],
        ]);

        $row = fn ($id, $nota, $waktu, $produk, $pel, $promo, $trx, $qty, $kotor, $diskon, $hpp) => [
            'id_detail_penjualan' => $id, 'id_penjualan' => $nota, 'waktu_key' => $waktu, 'produk_key' => $produk,
            'pelanggan_key' => $pel, 'promo_key' => $promo, 'transaksi_key' => $trx, 'jumlah' => $qty,
            'nilai_kotor' => $kotor, 'diskon_promo' => $diskon, 'diskon_poin' => 0,
            'pendapatan_bersih' => $kotor - $diskon, 'hpp' => $hpp, 'laba_kotor' => $kotor - $diskon - $hpp,
            'poin_didapat' => 10, 'poin_ditukar' => 0,
        ];
        // Nota N1 (2 baris, member online, Januari) dan N2 (1 baris, guest offline dengan promo, Februari)
        $dw->table('fact_penjualan')->insert([
            $row('D1', 'N1', 20260105, 1, 1, 0, 1, 3, 900000, 0, 450000),
            $row('D2', 'N1', 20260105, 2, 1, 0, 1, 1, 300000, 0, 150000),
            $row('D3', 'N2', 20260210, 2, 0, 1, 2, 1, 500000, 50000, 300000),
        ]);
        $dw->table('fact_pembelian')->insert([
            ['waktu_key' => 20260105, 'produk_key' => 1, 'supplier_key' => 1, 'id_pembelian' => 'B1', 'id_detail_pembelian' => 'BD1', 'jumlah' => 10, 'total_biaya' => 600000],
            ['waktu_key' => 20260110, 'produk_key' => 2, 'supplier_key' => 1, 'id_pembelian' => 'B2', 'id_detail_pembelian' => 'BD2', 'jumlah' => 2, 'total_biaya' => 400000],
        ]);
        $dw->table('fact_pengeluaran')->insert([
            ['waktu_key' => 20260110, 'id_pengeluaran' => 'E1', 'jenis_pengeluaran' => 'LISTRIK', 'nominal' => 100000],
        ]);
        $dw->table('etl_validasi')->insert([
            'dijalankan_pada' => '2026-11-30 10:00:00', 'kode' => 'V01', 'pemeriksaan' => 'uji',
            'nilai_oltp' => 3, 'nilai_dw' => 3, 'status' => 'PASS',
        ]);
    }
}

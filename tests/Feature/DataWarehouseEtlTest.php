<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Services\SaleService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataWarehouseEtlTest extends TestCase
{
    use RefreshDatabase;

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

        Artisan::call('migrate', [
            '--database' => 'dw',
            '--path' => 'database/migrations/dw',
            '--force' => true,
        ]);

        $this->seed(PrototypeSeeder::class);
    }

    public function test_etl_commands_reconcile_oltp_and_dw_across_all_facts(): void
    {
        $this->artisan('dw:sync')->assertSuccessful();

        $dw = DB::connection('dw');

        // Cek baris khusus key 0 dan dimensi statis
        $this->assertDatabaseHas('dim_customer', ['customer_key' => 0, 'nama' => 'GUEST / Non-member'], 'dw');
        $this->assertDatabaseHas('dim_staf', ['staf_key' => 0, 'nama' => 'Tidak ada (online)'], 'dw');
        $this->assertDatabaseHas('dim_promo', ['promo_key' => 0, 'nama_promo' => 'Tanpa promo'], 'dw');
        $this->assertSame(4, $dw->table('dim_transaksi')->count());
        $this->assertSame(7, $dw->table('dim_jenis_pengeluaran')->count());
        $this->assertGreaterThan(365, $dw->table('dim_waktu')->count());

        // Rekonsiliasi fact_penjualan
        $oltpSalesAgg = DB::table('detail_penjualan as d')
            ->join('penjualan as p', 'p.id_penjualan', '=', 'd.id_penjualan')
            ->where('p.status_pembayaran', 'BERHASIL')
            ->where('p.status_penjualan', '!=', 'BATAL')
            ->selectRaw('COUNT(*) as jml_item, SUM(d.jumlah * d.harga_satuan) as subtotal')
            ->first();

        $dwSalesAgg = $dw->table('fact_penjualan')
            ->selectRaw('COUNT(*) as jml_item, SUM(subtotal) as subtotal, SUM(pendapatan_bersih) as pendapatan_bersih')
            ->first();

        $this->assertSame((int) $oltpSalesAgg->jml_item, (int) $dwSalesAgg->jml_item);
        $this->assertEqualsWithDelta((float) $oltpSalesAgg->subtotal, (float) $dwSalesAgg->subtotal, 0.05);

        $oltpNominalSum = (float) DB::table('penjualan')
            ->where('status_pembayaran', 'BERHASIL')
            ->where('status_penjualan', '!=', 'BATAL')
            ->sum('nominal_bayar');
        $this->assertEqualsWithDelta($oltpNominalSum, (float) $dwSalesAgg->pendapatan_bersih, 0.05);

        // Rekonsiliasi fact_pembelian
        $oltpPurchaseAgg = DB::table('detail_pembelian as dp')
            ->join('pembelian_produk as pb', 'pb.id_pembelian', '=', 'dp.id_pembelian')
            ->where('pb.status_pembelian', 'DITERIMA')
            ->selectRaw('COUNT(*) as jml_item, SUM(dp.jumlah * dp.harga_satuan) as total_biaya')
            ->first();
        $dwPurchaseAgg = $dw->table('fact_pembelian')
            ->selectRaw('COUNT(*) as jml_item, SUM(total_biaya) as total_biaya')
            ->first();

        $this->assertSame((int) $oltpPurchaseAgg->jml_item, (int) $dwPurchaseAgg->jml_item);
        $this->assertEqualsWithDelta((float) $oltpPurchaseAgg->total_biaya, (float) $dwPurchaseAgg->total_biaya, 0.05);

        // Rekonsiliasi fact_pengeluaran
        $this->assertSame(DB::table('pengeluaran')->count(), $dw->table('fact_pengeluaran')->count());
        $this->assertEqualsWithDelta(
            (float) DB::table('pengeluaran')->sum('nominal'),
            (float) $dw->table('fact_pengeluaran')->sum('nominal'),
            0.05
        );

        // Periodic snapshot stok
        $this->assertSame(DB::table('varian_produk')->count(), $dw->table('fact_stok_snapshot')->count());
    }

    public function test_incremental_sale_uses_zero_keys_and_is_idempotent(): void
    {
        $this->artisan('dw:sync')->assertSuccessful();

        $dw = DB::connection('dw');
        $initialTxCount  = (int) $dw->table('fact_penjualan')->distinct('id_penjualan')->count('id_penjualan');
        $initialRowCount = (int) $dw->table('fact_penjualan')->count();

        $variant = DB::table('varian_produk')->where('id_varian', 'VAR0001')->first();
        $sales = app(SaleService::class);

        // Transaksi offline guest (harus masuk dengan customer_key = 0, promo_key > 0 karena PRD001 promo aktif)
        $guestSaleId = $sales->checkoutOffline(
            'KSR001',
            [$variant->id_varian => 1],
            null,
            'TUNAI',
            0
        );

        // Jalankan ETL dua kali untuk memastikan idempotensi (tidak ada duplikat)
        $this->artisan('dw:sync')->assertSuccessful();
        $this->artisan('dw:sync')->assertSuccessful();

        $afterTxCount  = (int) $dw->table('fact_penjualan')->distinct('id_penjualan')->count('id_penjualan');
        $afterRowCount = (int) $dw->table('fact_penjualan')->count();

        $this->assertSame($initialTxCount + 1, $afterTxCount);
        $this->assertSame($initialRowCount + 1, $afterRowCount);

        $dwRow = $dw->table('fact_penjualan')->where('id_penjualan', $guestSaleId)->first();
        $this->assertNotNull($dwRow);
        $this->assertSame(0, (int) $dwRow->customer_key);
        $this->assertGreaterThan(0, (int) $dwRow->staf_key);
        $this->assertGreaterThan(0, (int) $dwRow->promo_key);
        $this->assertGreaterThan(0.0, (float) $dwRow->diskon_promo);
        $this->assertGreaterThan(0.0, (float) $dwRow->hpp);
    }

    public function test_olap_operations_execute_cleanly_on_dw(): void
    {
        $this->artisan('dw:sync')->assertSuccessful();
        $dw = DB::connection('dw');

        // Roll-up bulanan
        $rollUp = $dw->table('fact_penjualan as f')
            ->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_key')
            ->groupBy('w.tahun', 'w.bulan', 'w.nama_bulan')
            ->selectRaw('w.tahun, w.bulan, w.nama_bulan, SUM(f.pendapatan_bersih) as omzet')
            ->get();
        $this->assertGreaterThanOrEqual(12, $rollUp->count());

        // Drill-down kategori -> produk -> ukuran
        $drillDown = $dw->table('fact_penjualan as f')
            ->join('dim_produk as p', 'p.produk_key', '=', 'f.produk_key')
            ->where('p.nama_kategori', 'Kaos')
            ->groupBy('p.nama_kategori', 'p.nama_produk', 'p.ukuran')
            ->selectRaw('p.nama_kategori, p.nama_produk, p.ukuran, SUM(f.jumlah) as qty')
            ->get();
        $this->assertNotEmpty($drillDown);

        // Pivot kategori x kanal
        $pivot = $dw->table('fact_penjualan as f')
            ->join('dim_produk as p', 'p.produk_key', '=', 'f.produk_key')
            ->join('dim_transaksi as t', 't.transaksi_key', '=', 'f.transaksi_key')
            ->groupBy('p.nama_kategori')
            ->selectRaw("
                p.nama_kategori,
                SUM(CASE WHEN t.kanal_penjualan = 'ONLINE' THEN f.pendapatan_bersih ELSE 0 END) as online_omzet,
                SUM(CASE WHEN t.kanal_penjualan = 'OFFLINE' THEN f.pendapatan_bersih ELSE 0 END) as offline_omzet
            ")
            ->get();
        $this->assertCount(6, $pivot);
    }

    public function test_analytics_dashboard_enforces_rbac_and_renders_dw_charts(): void
    {
        $admin    = Akun::findOrFail('ADM021');
        $cashier  = Akun::findOrFail('KSR001');
        $customer = Akun::findOrFail('CUST123');

        $this->actingAs($cashier)->get(route('admin.analitik'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.analitik'))->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.analitik.sync'))
            ->assertRedirect(route('admin.analitik'));

        $this->actingAs($admin)
            ->get(route('admin.analitik', [
                'tahun' => now()->year,
                'kanal' => 'ONLINE',
            ]))
            ->assertOk()
            ->assertSee('Dashboard Analitik Data Warehouse (OLAP)')
            ->assertSee('Tren Omzet &amp; Laba Kotor per Bulan', false)
            ->assertSee('Efektivitas Promo');

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Laporan Operasional (OLTP)')
            ->assertSee('Stok Hampir Habis / Minimum');
    }
}

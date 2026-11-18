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

    public function test_etl_commands_populate_star_schema_dimensions_and_facts_accurately(): void
    {
        $this->artisan('dw:etl-pembelian')->assertSuccessful();
        $this->artisan('dw:etl-penjualan')->assertSuccessful();
        $this->artisan('dw:etl-pengeluaran')->assertSuccessful();

        $dw = DB::connection('dw');

        $expectedPurchases = DB::table('detail_pembelian as d')
            ->join('pembelian_produk as p', 'd.id_pembelian', '=', 'p.id_pembelian')
            ->where('p.status_pembelian', 'DITERIMA')
            ->count();

        $expectedSalesRows = DB::table('detail_penjualan as d')
            ->join('penjualan as p', 'd.id_penjualan', '=', 'p.id_penjualan')
            ->where('p.status_pembayaran', 'BERHASIL')
            ->where('p.status_penjualan', '!=', 'BATAL')
            ->count();

        $expectedExpenses = DB::table('pengeluaran')->count();

        $this->assertSame($expectedPurchases, $dw->table('fakta_pembelian_produk')->count());
        $this->assertSame($expectedSalesRows, $dw->table('fakta_penjualan')->count());
        $this->assertSame($expectedExpenses, $dw->table('fakta_pengeluaran')->count());

        $oltpPaidSum = (float) DB::table('penjualan')
            ->where('status_pembayaran', 'BERHASIL')
            ->where('status_penjualan', '!=', 'BATAL')
            ->sum('nominal_bayar');

        $dwNetSum = (float) $dw->table('fakta_penjualan')->sum('total_bersih_item');

        $this->assertEqualsWithDelta($oltpPaidSum, $dwNetSum, 0.05);
    }

    public function test_incremental_sale_updates_dw_without_duplication(): void
    {
        $this->artisan('dw:sync')->assertSuccessful();

        $dw = DB::connection('dw');
        $initialTxCount = (int) $dw->table('fakta_penjualan')
            ->distinct('no_penjualan')
            ->count('no_penjualan');
        $initialRowCount = (int) $dw->table('fakta_penjualan')->count();

        $variant = DB::table('varian_produk')->where('stok', '>', 2)->first();
        $sales = app(SaleService::class);
        $newSaleId = $sales->checkoutOffline(
            'KSR001',
            [$variant->id_varian => 1],
            'CUST123',
            'QR',
            0
        );

        // Run ETL twice to verify both incremental update and idempotency (no duplicates)
        $this->artisan('dw:sync')->assertSuccessful();
        $this->artisan('dw:sync')->assertSuccessful();

        $afterTxCount = (int) $dw->table('fakta_penjualan')
            ->distinct('no_penjualan')
            ->count('no_penjualan');
        $afterRowCount = (int) $dw->table('fakta_penjualan')->count();

        $this->assertSame($initialTxCount + 1, $afterTxCount);
        $this->assertSame($initialRowCount + 1, $afterRowCount);
        $this->assertSame(
            1,
            $dw->table('fakta_penjualan')->where('no_penjualan', $newSaleId)->count()
        );
    }

    public function test_admin_can_sync_and_filter_dw_analytics_dashboard(): void
    {
        $admin = Akun::findOrFail('ADM021');

        $this->actingAs($admin)
            ->post(route('admin.reports.sync-dw'))
            ->assertRedirect(route('admin.reports.index'));

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'periode' => 'semua',
                'kanal' => 'ONLINE',
            ]))
            ->assertOk()
            ->assertSee('Dashboard Analitik Data Warehouse (OLAP)')
            ->assertSee('Stok Hampir Habis / Minimum')
            ->assertSee('Rasio Kanal Penjualan');
    }
}

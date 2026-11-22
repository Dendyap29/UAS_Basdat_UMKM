<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrototypeSeeder extends Seeder
{
    /** Awal dan akhir data transaksi (12 bulan: Des 2025 - Nov 2026). */
    public const DATA_START = '2025-12-01 00:00:00';
    public const DATA_END = '2026-11-30 23:59:00';

    /**
     * Insert rows in SQLite-safe chunks (<= 100 rows per batch).
     */
    private function insertChunked(string $table, array $rows, int $chunkSize = 100): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    public function run(): void
    {
        mt_srand(20261119);

        DB::transaction(function (): void {
            // Periode data TETAP (12 bulan kalender penuh), tidak bergantung tanggal
            // menjalankan seeder, sehingga hasilnya sama di semua laptop.
            $now = Carbon::parse(self::DATA_END);
            $startPeriod = Carbon::parse(self::DATA_START);

            /*
            |--------------------------------------------------------------------------
            | 1. AKUN (Admin, Kasir, dan Customer 160 akun)
            |--------------------------------------------------------------------------
            */
            $kasirHash    = Hash::make('kasir123');
            $adminHash    = Hash::make('admin123');
            $customerHash = Hash::make('customer123');

            $accounts = [
                [
                    'id_akun' => 'CUST123',
                    'nama' => 'Budi Santoso',
                    'username' => 'budi',
                    'password_hash' => Hash::make('budi123'),
                    'email' => 'budi.santoso@example.com',
                    'no_telp' => '081234567812',
                    'alamat' => 'Jl. Ketintang Baru, Surabaya',
                    'tipe_akun' => 'CUSTOMER',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(350),
                    'updated_at' => $now->copy()->subDays(350),
                ],
                [
                    'id_akun' => 'CUST124',
                    'nama' => 'Alya Putri',
                    'username' => 'alya',
                    'password_hash' => Hash::make('alya123'),
                    'email' => 'alya.putri@example.com',
                    'no_telp' => '082112345678',
                    'alamat' => 'Jl. Dharmawangsa, Surabaya',
                    'tipe_akun' => 'CUSTOMER',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(340),
                    'updated_at' => $now->copy()->subDays(60),
                ],
                [
                    'id_akun' => 'CUST125',
                    'nama' => 'Raka Mahendra',
                    'username' => 'raka',
                    'password_hash' => Hash::make('raka123'),
                    'email' => 'raka.mahendra@example.com',
                    'no_telp' => '085156739204',
                    'alamat' => 'Jl. Ngagel Jaya, Surabaya',
                    'tipe_akun' => 'CUSTOMER',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(330),
                    'updated_at' => $now->copy()->subDays(42),
                ],
                [
                    'id_akun' => 'CUST126',
                    'nama' => 'Nadia Prameswari',
                    'username' => 'nadia',
                    'password_hash' => Hash::make('nadia123'),
                    'email' => 'nadia.prameswari@example.com',
                    'no_telp' => '083874216509',
                    'alamat' => 'Jl. Mulyorejo, Surabaya',
                    'tipe_akun' => 'CUSTOMER',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(320),
                    'updated_at' => $now->copy()->subDays(30),
                ],
                [
                    'id_akun' => 'KSR001',
                    'nama' => 'Siti Amalia',
                    'username' => 'siti',
                    'password_hash' => $kasirHash,
                    'email' => 'siti.amalia@example.com',
                    'no_telp' => '085712349876',
                    'alamat' => 'Jl. Rungkut Asri, Surabaya',
                    'tipe_akun' => 'KASIR',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(365),
                    'updated_at' => $now->copy()->subDays(12),
                ],
                [
                    'id_akun' => 'KSR002',
                    'nama' => 'Rizky Ramadhan',
                    'username' => 'rizky',
                    'password_hash' => $kasirHash,
                    'email' => 'rizky.ramadhan@example.com',
                    'no_telp' => '081298765431',
                    'alamat' => 'Jl. Manyar Kertoarjo, Surabaya',
                    'tipe_akun' => 'KASIR',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(365),
                    'updated_at' => $now->copy()->subDays(9),
                ],
                [
                    'id_akun' => 'KSR003',
                    'nama' => 'Nabila Rahma',
                    'username' => 'nabila',
                    'password_hash' => $kasirHash,
                    'email' => 'nabila.rahma@example.com',
                    'no_telp' => '087746251903',
                    'alamat' => 'Jl. Wonokromo, Surabaya',
                    'tipe_akun' => 'KASIR',
                    'status_akun' => 'NONAKTIF',
                    'created_at' => $now->copy()->subDays(300),
                    'updated_at' => $now->copy()->subDays(18),
                ],
                [
                    'id_akun' => 'KSR004',
                    'nama' => 'Bagas Pratama',
                    'username' => 'bagas',
                    'password_hash' => $kasirHash,
                    'email' => 'bagas.pratama@example.com',
                    'no_telp' => '081345009911',
                    'alamat' => 'Jl. Gubeng Kertajaya, Surabaya',
                    'tipe_akun' => 'KASIR',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(350),
                    'updated_at' => $now->copy()->subDays(5),
                ],
                [
                    'id_akun' => 'ADM021',
                    'nama' => 'Nanda Pratama',
                    'username' => 'admin',
                    'password_hash' => $adminHash,
                    'email' => 'admin@forma.local',
                    'no_telp' => '087812345609',
                    'alamat' => 'Jl. Ahmad Yani, Surabaya',
                    'tipe_akun' => 'ADMIN',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(370),
                    'updated_at' => $now,
                ],
                [
                    'id_akun' => 'ADM022',
                    'nama' => 'Dinda Maharani',
                    'username' => 'dinda_adm',
                    'password_hash' => $adminHash,
                    'email' => 'dinda@forma.local',
                    'no_telp' => '087812345610',
                    'alamat' => 'Jl. Diponegoro, Surabaya',
                    'tipe_akun' => 'ADMIN',
                    'status_akun' => 'AKTIF',
                    'created_at' => $now->copy()->subDays(365),
                    'updated_at' => $now,
                ],
            ];

            $firstNames = ['Andi', 'Citra', 'Dimas', 'Eka', 'Farah', 'Gilang', 'Hana', 'Ilham', 'Jihan', 'Kevin', 'Laras', 'Maulana', 'Nisa', 'Omar', 'Putri', 'Qori', 'Reza', 'Salsa', 'Tari', 'Umar', 'Vina', 'Wahyu', 'Yuda', 'Zahra'];
            $lastNames  = ['Wijaya', 'Saputra', 'Kusuma', 'Permata', 'Hidayat', 'Nugroho', 'Pratama', 'Lestari', 'Santoso', 'Wibowo', 'Rahmawati', 'Fauzan'];
            $streets    = ['Jl. Sukolilo', 'Jl. Kertajaya', 'Jl. Merr', 'Jl. Tenggilis', 'Jl. Jemursari', 'Jl. Wiyung', 'Jl. Rungkut', 'Jl. Gubeng'];

            $activeCustomerIds = ['CUST123', 'CUST124', 'CUST125', 'CUST126'];
            $customerAddresses = [
                'CUST123' => 'Jl. Ketintang Baru, Surabaya',
                'CUST124' => 'Jl. Dharmawangsa, Surabaya',
                'CUST125' => 'Jl. Ngagel Jaya, Surabaya',
                'CUST126' => 'Jl. Mulyorejo, Surabaya',
            ];

            for ($i = 1; $i <= 156; $i++) {
                $custId = 'CUST'.str_pad(200 + $i, 3, '0', STR_PAD_LEFT);
                $fullName = $firstNames[$i % count($firstNames)].' '.$lastNames[($i * 3) % count($lastNames)];
                $address = $streets[$i % count($streets)].' No. '.(10 + $i).', Surabaya';
                $regDaysAgo = mt_rand(30, 360);
                $regDate = $now->copy()->subDays($regDaysAgo);

                $accounts[] = [
                    'id_akun' => $custId,
                    'nama' => $fullName,
                    'username' => 'member'.$i,
                    'password_hash' => $customerHash,
                    'email' => "member{$i}@example.com",
                    'no_telp' => '0819'.str_pad($i, 8, '0', STR_PAD_LEFT),
                    'alamat' => $address,
                    'tipe_akun' => 'CUSTOMER',
                    'status_akun' => 'AKTIF',
                    'created_at' => $regDate,
                    'updated_at' => $regDate,
                ];

                $activeCustomerIds[] = $custId;
                $customerAddresses[$custId] = $address;
            }

            $this->insertChunked('akun', $accounts);

            /*
            |--------------------------------------------------------------------------
            | 2. KATEGORI PRODUK (6 Kategori)
            |--------------------------------------------------------------------------
            */
            DB::table('kategori_produk')->insert([
                ['id_kategori' => 'KTG001', 'nama_kategori' => 'Kaos', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_kategori' => 'KTG002', 'nama_kategori' => 'Hoodie', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_kategori' => 'KTG003', 'nama_kategori' => 'Sweater', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_kategori' => 'KTG004', 'nama_kategori' => 'Kemeja', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_kategori' => 'KTG005', 'nama_kategori' => 'Celana', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_kategori' => 'KTG006', 'nama_kategori' => 'Jaket', 'created_at' => $startPeriod, 'updated_at' => $now],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3. PRODUK (32 Produk)
            |--------------------------------------------------------------------------
            */
            $productDefs = [
                ['PRD001', 'KTG001', 'Basic T-Shirt', 119000, 'basic-tshirt.jpg', 'Kaos basic cotton combed 24s untuk pemakaian harian.'],
                ['PRD002', 'KTG001', 'Oversized T-Shirt', 149000, 'oversized-tshirt.jpg', 'Kaos potongan oversized santai dengan bahan tebal.'],
                ['PRD003', 'KTG001', 'Pocket T-Shirt', 139000, 'pocket-tshirt.jpg', 'Kaos minimalis dengan detail saku depan.'],
                ['PRD004', 'KTG002', 'Pullover Hoodie', 259000, 'hoodie.jpg', 'Hoodie fleece lembut untuk gaya kasual harian.'],
                ['PRD005', 'KTG003', 'Crewneck Sweatshirt', 229000, 'crewneck.jpg', 'Sweatshirt crewneck nyaman untuk aktivitas sehari-hari.'],
                ['PRD006', 'KTG004', 'Casual Shirt', 189000, 'casual-shirt.jpg', 'Kemeja kasual clean fit berbahan katun poplin.'],
                ['PRD007', 'KTG004', 'Overshirt', 219000, 'overshirt.jpg', 'Overshirt twill ringan untuk layering kasual.'],
                ['PRD008', 'KTG005', 'Cargo Pants', 229000, 'cargo-pants.jpg', 'Celana cargo katun ripstop dengan banyak saku fungsional.'],
                ['PRD009', 'KTG005', 'Straight Pants', 209000, 'straight-pants.jpg', 'Celana chino straight fit rapi untuk kerja maupun santai.'],
                ['PRD010', 'KTG001', 'Long Sleeve Tee', 155000, 'long-sleeve.jpg', 'Kaos lengan panjang ringan dengan rib di pergelangan.'],
                ['PRD011', 'KTG001', 'Heavyweight Boxy Tee', 165000, 'oversized-tshirt.jpg', 'Kaos boxy fit 16s dengan kerah tebal.'],
                ['PRD012', 'KTG001', 'Striped Rib Tee', 145000, 'basic-tshirt.jpg', 'Kaos motif garis klasik dengan tekstur halus.'],
                ['PRD013', 'KTG001', 'Polo Knit Shirt', 179000, 'casual-shirt.jpg', 'Kaos polo rajut ringan bernuansa smart casual.'],
                ['PRD014', 'KTG002', 'Zip-Up Hoodie', 279000, 'hoodie.jpg', 'Hoodie ritsleting penuh dengan kantong kanguru.'],
                ['PRD015', 'KTG002', 'Boxy Fleece Hoodie', 289000, 'hoodie.jpg', 'Hoodie potongan boxy dengan bahan heavy fleece.'],
                ['PRD016', 'KTG002', 'Lightweight French Terry Hoodie', 249000, 'hoodie.jpg', 'Hoodie french terry adem untuk iklim tropis.'],
                ['PRD017', 'KTG003', 'Cable Knit Sweater', 269000, 'crewneck.jpg', 'Sweater rajut motif cable yang hangat dan rapi.'],
                ['PRD018', 'KTG003', 'Half-Zip Sweatshirt', 255000, 'crewneck.jpg', 'Sweatshirt kerah setengah ritsleting gaya modern.'],
                ['PRD019', 'KTG003', 'Raglan Fleece Crewneck', 219000, 'crewneck.jpg', 'Crewneck lengan raglan dengan detail jahitan kontras.'],
                ['PRD020', 'KTG004', 'Oxford Button-Down Shirt', 209000, 'casual-shirt.jpg', 'Kemeja oxford klasik cocok untuk kuliah dan kantor.'],
                ['PRD021', 'KTG004', 'Linen Camp Collar Shirt', 199000, 'casual-shirt.jpg', 'Kemeja kerah camp berbahan linen blend yang sejuk.'],
                ['PRD022', 'KTG004', 'Flannel Check Shirt', 225000, 'overshirt.jpg', 'Kemeja flanel kotak-kotak lembut dan tahan lama.'],
                ['PRD023', 'KTG004', 'Utility Work Shirt', 235000, 'overshirt.jpg', 'Kemeja workwear dua saku dengan kancing tebal.'],
                ['PRD024', 'KTG005', 'Pleated Wide Chino', 239000, 'straight-pants.jpg', 'Celana chino wide leg dengan detail lipit depan.'],
                ['PRD025', 'KTG005', 'Tapered Ankle Pants', 219000, 'straight-pants.jpg', 'Celana ankle fit ringan untuk tampilan minimalis.'],
                ['PRD026', 'KTG005', 'Relaxed Denim Jeans', 289000, 'cargo-pants.jpg', 'Celana denim 13oz dengan potongan santai.'],
                ['PRD027', 'KTG005', 'Drawstring Linen Pants', 195000, 'straight-pants.jpg', 'Celana pinggang tali serut yang nyaman dipakai seharian.'],
                ['PRD028', 'KTG006', 'Coach Windbreaker Jacket', 319000, 'overshirt.jpg', 'Jaket coach tahan angin dengan furing jaring.'],
                ['PRD029', 'KTG006', 'Corduroy Harrington Jacket', 349000, 'overshirt.jpg', 'Jaket harrington bahan korduroi premium.'],
                ['PRD030', 'KTG006', 'Denim Trucker Jacket', 369000, 'overshirt.jpg', 'Jaket denim klasik dengan dua saku dada.'],
                ['PRD031', 'KTG006', 'Canvas Work Jacket', 359000, 'overshirt.jpg', 'Jaket kanvas tebal bergaya heritage workwear.'],
                ['PRD032', 'KTG006', 'Puffer Vest Jacket', 299000, 'hoodie.jpg', 'Rompi jaket empuk untuk layering cuaca dingin.'],
            ];

            $products = [];
            $productPrices = [];
            $productCategories = [];

            foreach ($productDefs as $idx => [$id, $kat, $name, $price, $img, $desc]) {
                $products[] = [
                    'id_produk' => $id,
                    'id_kategori' => $kat,
                    'nama_produk' => $name,
                    'harga_jual' => $price,
                    'deskripsi' => $desc,
                    'gambar_produk' => $img,
                    'status_produk' => 'AKTIF',
                    'created_at' => $startPeriod->copy()->subDays(10),
                    'updated_at' => $now,
                ];
                $productPrices[$id] = (float) $price;
                $productCategories[$id] = $kat;
            }

            $this->insertChunked('produk', $products);

            /*
            |--------------------------------------------------------------------------
            | 4. VARIAN PRODUK (Ukuran S, M, L, XL, XXL x 2 Warna per Produk)
            |--------------------------------------------------------------------------
            | VAR0001..VAR0004 tetap milik PRD001 agar test smoke konsisten.
            */
            $colorPairs = [
                'KTG001' => ['hitam', 'putih'],
                'KTG002' => ['hitam', 'abu-abu'],
                'KTG003' => ['hitam', 'navy'],
                'KTG004' => ['putih', 'navy'],
                'KTG005' => ['khaki', 'hitam'],
                'KTG006' => ['olive', 'hitam'],
            ];
            $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
            // Bobot permintaan ukuran (M & L paling laku sesuai pola pasar)
            $sizeWeights = ['S' => 15, 'M' => 35, 'L' => 30, 'XL' => 14, 'XXL' => 6];

            $variantRows = [];
            $variantMeta = [];
            $variantIds = [];
            $variantStock = [];
            $variantNum = 1;

            foreach ($productDefs as [$prodId, $katId, $name, $price]) {
                $colors = $colorPairs[$katId];
                $firstSizes = ($prodId === 'PRD001') ? ['S', 'M', 'L', 'XL'] : $sizes;

                foreach ($colors as $cIndex => $warna) {
                    $sizeList = ($prodId === 'PRD001' && $cIndex === 0) ? $firstSizes : $sizes;
                    foreach ($sizeList as $ukuran) {
                        $varId = 'VAR'.str_pad($variantNum++, 4, '0', STR_PAD_LEFT);
                        $variantRows[] = [
                            'id_varian' => $varId,
                            'id_produk' => $prodId,
                            'ukuran' => $ukuran,
                            'warna' => $warna,
                            'stok' => 0,
                            'stok_minimum' => 5,
                            'created_at' => $startPeriod,
                            'updated_at' => $now,
                        ];
                        $variantMeta[$varId] = [
                            'id_produk' => $prodId,
                            'id_kategori' => $katId,
                            'ukuran' => $ukuran,
                            'warna' => $warna,
                            'harga_jual' => (float) $price,
                            'weight' => $sizeWeights[$ukuran] ?? 10,
                        ];
                        $variantIds[] = $varId;
                        $variantStock[$varId] = 0;
                    }
                }
            }

            $this->insertChunked('varian_produk', $variantRows);

            /*
            |--------------------------------------------------------------------------
            | 5. SUPPLIER (6 Supplier)
            |--------------------------------------------------------------------------
            */
            $suppliers = [
                ['id_supplier' => 'SUP001', 'nama_supplier' => 'Nusantara Apparel Supply', 'no_telp' => '081357924681', 'alamat' => 'Jl. Margomulyo Indah, Surabaya', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_supplier' => 'SUP002', 'nama_supplier' => 'Urban Garment Wholesale', 'no_telp' => '082233764915', 'alamat' => 'Jl. Kopo, Bandung', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_supplier' => 'SUP003', 'nama_supplier' => 'Dailywear Distributor', 'no_telp' => '085889214376', 'alamat' => 'Jl. Pademangan, Jakarta Utara', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_supplier' => 'SUP004', 'nama_supplier' => 'Prima Fashion Supply', 'no_telp' => '081376542890', 'alamat' => 'Jl. Waru Industri, Sidoarjo', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_supplier' => 'SUP005', 'nama_supplier' => 'Bandung Textile Mandiri', 'no_telp' => '081244556677', 'alamat' => 'Jl. Cihampelas, Bandung', 'created_at' => $startPeriod, 'updated_at' => $now],
                ['id_supplier' => 'SUP006', 'nama_supplier' => 'Solo Konveksi Sejahtera', 'no_telp' => '085633221100', 'alamat' => 'Jl. Slamet Riyadi, Surakarta', 'created_at' => $startPeriod, 'updated_at' => $now],
            ];
            DB::table('supplier')->insert($suppliers);

            /*
            |--------------------------------------------------------------------------
            | 6. PROMO (10 promo; semua tanggal TETAP)
            |--------------------------------------------------------------------------
            | PRM001 sengaja menjadi satu-satunya promo yang aktif untuk PRD001 pada
            | akhir periode, supaya FinalSystemSmokeTest tetap deterministik.
            */
            $promoDefs = [
                ['PRM001', 'Everyday Essentials', 10, Carbon::parse('2026-11-10 00:00:00'), Carbon::parse('2026-12-31 23:59:59'), ['PRD001', 'PRD002', 'PRD006', 'PRD010', 'PRD013']],
                ['PRM002', 'Outerwear Week', 15, Carbon::parse('2026-11-23 00:00:00'), Carbon::parse('2026-12-06 23:59:59'), ['PRD004', 'PRD005', 'PRD007', 'PRD028', 'PRD029']],
                ['PRM003', 'Weekend Bottoms', 20, Carbon::parse('2026-10-31 00:00:00'), Carbon::parse('2026-11-09 23:59:59'), ['PRD008', 'PRD009', 'PRD024', 'PRD025']],
                ['PRM004', 'Year-Start Clearance', 15, $startPeriod->copy()->addDays(5), $startPeriod->copy()->addDays(35)->endOfDay(), ['PRD001', 'PRD003', 'PRD011', 'PRD014', 'PRD020']],
                ['PRM005', 'Ramadhan Gaya Bersih', 25, $startPeriod->copy()->addDays(70), $startPeriod->copy()->addDays(105)->endOfDay(), ['PRD006', 'PRD007', 'PRD020', 'PRD021', 'PRD022', 'PRD024']],
                ['PRM006', 'Mid-Year Casual Fest', 12, $startPeriod->copy()->addDays(145), $startPeriod->copy()->addDays(175)->endOfDay(), ['PRD002', 'PRD010', 'PRD012', 'PRD016', 'PRD027']],
                ['PRM007', 'Independence Sale 17%', 17, Carbon::parse('2026-08-10 00:00:00'), Carbon::parse('2026-08-20 23:59:59'), ['PRD001', 'PRD004', 'PRD015', 'PRD023', 'PRD030']],
                ['PRM008', 'Autumn Layering Deals', 18, Carbon::parse('2026-09-07 00:00:00'), Carbon::parse('2026-10-07 23:59:59'), ['PRD005', 'PRD017', 'PRD018', 'PRD028', 'PRD031']],
                ['PRM009', 'Twin-Date Flash Promo 11.11', 30, Carbon::parse('2026-11-11 00:00:00'), Carbon::parse('2026-11-12 23:59:59'), ['PRD003', 'PRD014', 'PRD019', 'PRD026', 'PRD032']],
                ['PRM010', 'Payday Collection', 12, Carbon::parse('2026-12-04 00:00:00'), Carbon::parse('2026-12-12 23:59:59'), ['PRD003', 'PRD008', 'PRD026']],
            ];

            $promoRows = [];
            $promoProductRows = [];
            $promoLookupByProduct = [];

            foreach ($promoDefs as [$prmId, $prmName, $pct, $mulai, $selesai, $prodList]) {
                $promoRows[] = [
                    'id_promo' => $prmId,
                    'nama_promo' => $prmName,
                    'persen_diskon' => $pct,
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                    'created_at' => $mulai->copy()->subDays(2),
                    'updated_at' => $mulai->copy()->subDays(2),
                ];
                foreach ($prodList as $pid) {
                    $promoProductRows[] = [
                        'id_promo' => $prmId,
                        'id_produk' => $pid,
                    ];
                    $promoLookupByProduct[$pid][] = [
                        'id_promo' => $prmId,
                        'persen_diskon' => (float) $pct,
                        'mulai' => $mulai,
                        'selesai' => $selesai,
                    ];
                }
            }

            DB::table('promo')->insert($promoRows);
            DB::table('promo_produk')->insert($promoProductRows);

            /*
            |--------------------------------------------------------------------------
            | 7. STOK AWAL + PENGELUARAN OPERASIONAL
            |--------------------------------------------------------------------------
            | Toko membuka stok pada 1 Desember 2025: 6 pembelian awal (satu per
            | supplier) diterima pukul 08:00, sebelum transaksi pertama pukul 09:00.
            | Jumlahnya hanya setara kebutuhan sekitar 1-2 bulan (stok target per
            | ukuran), bukan stok setahun. Restock berikutnya dibuat otomatis oleh
            | simulasi harian (bagian 8) saat stok menyentuh titik pesan ulang.
            */
            $purchaseRows = [];
            $purchaseDetailRows = [];
            $expenseRows = [];
            $mutasiRows = [];

            $pbNum = 1;
            $dpbNum = 1;
            $pngNum = 1;
            $supplierIds = array_column($suppliers, 'id_supplier');
            $adminIds = ['ADM021', 'ADM022'];

            // Stok target & titik pesan ulang per ukuran
            $stockTarget  = ['S' => 9, 'M' => 13, 'L' => 13, 'XL' => 9, 'XXL' => 8];
            $reorderPoint = ['S' => 6, 'M' => 8, 'L' => 8, 'XL' => 6, 'XXL' => 6];

            // Setiap produk selalu dibeli dari supplier yang sama
            $supplierOfProduct = [];
            foreach ($productDefs as $i => $def) {
                $supplierOfProduct[$def[0]] = $supplierIds[$i % count($supplierIds)];
            }

            $variantsBySupplier = [];
            foreach ($variantIds as $varId) {
                $variantsBySupplier[$supplierOfProduct[$variantMeta[$varId]['id_produk']]][] = $varId;
            }

            $openingOrder = $startPeriod->copy()->setTime(6, 0);
            $openingRecv  = $startPeriod->copy()->setTime(8, 0);
            $openingIdx = 0;

            foreach ($variantsBySupplier as $supId => $vars) {
                $pbId = 'PB'.str_pad($pbNum++, 4, '0', STR_PAD_LEFT);
                $admId = $adminIds[$openingIdx++ % 2];
                $poTotal = 0;

                foreach ($vars as $varId) {
                    $meta = $variantMeta[$varId];
                    $qty = $stockTarget[$meta['ukuran']];
                    $cost = round($meta['harga_jual'] * (0.55 + (mt_rand(0, 8) / 100)), -2);

                    $purchaseDetailRows[] = [
                        'id_detail_pembelian' => 'DPB'.str_pad($dpbNum++, 5, '0', STR_PAD_LEFT),
                        'id_pembelian' => $pbId,
                        'id_varian' => $varId,
                        'jumlah' => $qty,
                        'harga_satuan' => $cost,
                    ];

                    $variantStock[$varId] += $qty;
                    $poTotal += $qty * $cost;

                    $mutasiRows[] = [
                        'id_varian' => $varId,
                        'tanggal' => $openingRecv,
                        'jenis' => 'MASUK',
                        'jumlah' => $qty,
                        'stok_sesudah' => $variantStock[$varId],
                        'referensi' => $pbId,
                        'created_at' => $openingRecv,
                        'updated_at' => $openingRecv,
                    ];
                }

                $purchaseRows[] = [
                    'id_pembelian' => $pbId,
                    'id_supplier' => $supId,
                    'id_akun' => $admId,
                    'tanggal_pembelian' => $openingOrder,
                    'tanggal_diterima' => $openingRecv,
                    'status_pembelian' => 'DITERIMA',
                    'created_at' => $openingOrder,
                    'updated_at' => $openingRecv,
                ];

                $expenseRows[] = [
                    'id_pengeluaran' => 'PNG'.str_pad($pngNum++, 4, '0', STR_PAD_LEFT),
                    'id_akun' => $admId,
                    'id_pembelian' => $pbId,
                    'tanggal_pengeluaran' => $openingRecv,
                    'jenis_pengeluaran' => 'PEMBELIAN_PRODUK',
                    'nominal' => $poTotal,
                    'keterangan' => "Stok awal toko {$pbId} diterima",
                    'created_at' => $openingRecv,
                    'updated_at' => $openingRecv,
                ];
            }

            // Pengeluaran operasional rutin: satu set per bulan kalender (Des 2025 - Nov 2026)
            for ($m = 0; $m < 12; $m++) {
                $monthStart = $startPeriod->copy()->addMonthsNoOverflow($m)->startOfMonth();

                $opsItems = [
                    ['SEWA', 4500000, 3, 'Sewa ruko & gudang bulanan'],
                    ['LISTRIK', mt_rand(600000, 900000), 10, 'Tagihan listrik toko'],
                    ['INTERNET', 450000, 12, 'Internet & Wi-Fi kasir'],
                    ['ONGKIR', mt_rand(1500000, 2400000), 18, 'Biaya ekspedisi pesanan online'],
                    ['PERAWATAN', mt_rand(250000, 500000), 22, 'Perawatan toko & display'],
                ];
                if ($m % 2 === 1) {
                    $opsItems[] = ['LAINNYA', mt_rand(300000, 600000), 25, 'Perlengkapan kemasan & label'];
                }

                foreach ($opsItems as [$jenis, $nominal, $dayOfMonth, $ket]) {
                    $expDate = $monthStart->copy()->addDays($dayOfMonth - 1)->setTime(10, 0);
                    $expenseRows[] = [
                        'id_pengeluaran' => 'PNG'.str_pad($pngNum++, 4, '0', STR_PAD_LEFT),
                        'id_akun' => $adminIds[$m % 2],
                        'id_pembelian' => null,
                        'tanggal_pengeluaran' => $expDate,
                        'jenis_pengeluaran' => $jenis,
                        'nominal' => $nominal,
                        'keterangan' => $ket,
                        'created_at' => $expDate,
                        'updated_at' => $expDate,
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 8. SIMULASI HARIAN: PENJUALAN + RESTOCK OTOMATIS (1 Des 2025 - 30 Nov 2026)
            |--------------------------------------------------------------------------
            | Urutan tiap hari: (1) terima pembelian yang jatuh tempo, (2) Senin dan
            | Kamis cek stok & buat pembelian ke supplier, (3) catat penjualan.
            | Penjualan hanya mengambil stok yang tersedia, jadi stok tidak pernah minus.
            */
            $weightedVariantPool = [];
            foreach ($variantMeta as $varId => $meta) {
                $repeats = max(1, (int) round($meta['weight'] / 5));
                for ($r = 0; $r < $repeats; $r++) {
                    $weightedVariantPool[] = $varId;
                }
            }

            $salesRows = [];
            $salesMemberRows = [];
            $salesDetailRows = [];

            $pointBalances = array_fill_keys($activeCustomerIds, 0);
            $firstTransaction = [];
            $kasirIds = ['KSR001', 'KSR002', 'KSR004'];

            $pjNum = 1;
            $dpjNum = 1;

            $inbound = [];       // pembelian DITERIMA yang belum sampai
            $pendingQty = [];    // varian => jumlah yang sedang dipesan
            $totalDays = (int) $startPeriod->diffInDays($now->copy()->startOfDay());

            for ($day = 0; $day <= $totalDays; $day++) {
                $currentDate = $startPeriod->copy()->addDays($day);

                // (1) Terima pembelian yang jatuh tempo
                foreach ($inbound as $k => $rs) {
                    if ($rs['recv_date']->toDateString() > $currentDate->toDateString()) {
                        continue;
                    }
                    foreach ($rs['items'] as $varId => $qty) {
                        $variantStock[$varId] += $qty;
                        $pendingQty[$varId] -= $qty;
                        $mutasiRows[] = [
                            'id_varian' => $varId,
                            'tanggal' => $rs['recv_date'],
                            'jenis' => 'MASUK',
                            'jumlah' => $qty,
                            'stok_sesudah' => $variantStock[$varId],
                            'referensi' => $rs['pb_id'],
                            'created_at' => $rs['recv_date'],
                            'updated_at' => $rs['recv_date'],
                        ];
                    }
                    $expenseRows[] = [
                        'id_pengeluaran' => 'PNG'.str_pad($pngNum++, 4, '0', STR_PAD_LEFT),
                        'id_akun' => $rs['adm_id'],
                        'id_pembelian' => $rs['pb_id'],
                        'tanggal_pengeluaran' => $rs['recv_date'],
                        'jenis_pengeluaran' => 'PEMBELIAN_PRODUK',
                        'nominal' => $rs['total'],
                        'keterangan' => "Pembelian {$rs['pb_id']} diterima",
                        'created_at' => $rs['recv_date'],
                        'updated_at' => $rs['recv_date'],
                    ];
                    unset($inbound[$k]);
                }

                // (2) Senin & Kamis: pesan ulang varian yang stoknya menipis
                if ($day > 0 && in_array($currentDate->dayOfWeekIso, [1, 4], true)) {
                    $needsBySupplier = [];
                    foreach ($variantIds as $varId) {
                        $meta = $variantMeta[$varId];
                        $onHand = $variantStock[$varId] + ($pendingQty[$varId] ?? 0);
                        if ($onHand <= $reorderPoint[$meta['ukuran']]) {
                            $needsBySupplier[$supplierOfProduct[$meta['id_produk']]][$varId] =
                                $stockTarget[$meta['ukuran']] - $onHand;
                        }
                    }

                    foreach ($needsBySupplier as $supId => $items) {
                        $pbId = 'PB'.str_pad($pbNum++, 4, '0', STR_PAD_LEFT);
                        $admId = $adminIds[$pbNum % 2];
                        $orderDate = $currentDate->copy()->setTime(10, mt_rand(0, 59));
                        $recvDate = $currentDate->copy()->addDays(mt_rand(2, 4))->setTime(8, 30);

                        if ($recvDate->gt($now)) {
                            $status = 'DIPESAN';
                            $recvDate = null;
                        } elseif (mt_rand(1, 100) <= 6) {
                            $status = 'BATAL';
                            $recvDate = null;
                        } else {
                            $status = 'DITERIMA';
                        }

                        $poTotal = 0;
                        $poItems = [];
                        foreach ($items as $varId => $qty) {
                            $meta = $variantMeta[$varId];
                            $cost = round($meta['harga_jual'] * (0.55 + (mt_rand(0, 8) / 100)), -2);

                            $purchaseDetailRows[] = [
                                'id_detail_pembelian' => 'DPB'.str_pad($dpbNum++, 5, '0', STR_PAD_LEFT),
                                'id_pembelian' => $pbId,
                                'id_varian' => $varId,
                                'jumlah' => $qty,
                                'harga_satuan' => $cost,
                            ];

                            $poTotal += $qty * $cost;
                            $poItems[$varId] = $qty;

                            // DIPESAN masih ditunggu; BATAL tidak ditunggu
                            if ($status !== 'BATAL') {
                                $pendingQty[$varId] = ($pendingQty[$varId] ?? 0) + $qty;
                            }
                        }

                        $purchaseRows[] = [
                            'id_pembelian' => $pbId,
                            'id_supplier' => $supId,
                            'id_akun' => $admId,
                            'tanggal_pembelian' => $orderDate,
                            'tanggal_diterima' => $recvDate,
                            'status_pembelian' => $status,
                            'created_at' => $orderDate,
                            'updated_at' => $recvDate ?? $orderDate,
                        ];

                        if ($status === 'DITERIMA') {
                            $inbound[] = [
                                'pb_id' => $pbId,
                                'recv_date' => $recvDate,
                                'adm_id' => $admId,
                                'total' => $poTotal,
                                'items' => $poItems,
                            ];
                        }
                    }
                }

                // (3) Penjualan hari ini
                $isWeekend = in_array($currentDate->dayOfWeekIso, [6, 7], true);
                $isSeasonalPeak = $day <= 30 || ($day >= 70 && $day <= 105) || $day >= 330;
                $txToday = mt_rand(6, 9) + ($isWeekend ? 4 : 0) + ($isSeasonalPeak ? 3 : 0);
                $inFlightDay = $day >= $totalDays - 1; // 2 hari terakhir: pesanan online belum selesai

                for ($t = 0; $t < $txToday; $t++) {
                    $saleMoment = $currentDate->copy()->setTime(mt_rand(9, 21), mt_rand(0, 59), mt_rand(0, 59));

                    $isOnline = mt_rand(1, 100) <= 45;
                    $channel = $isOnline ? 'ONLINE' : 'OFFLINE';
                    $method = $isOnline ? 'QR' : (mt_rand(1, 100) <= 55 ? 'TUNAI' : 'QR');

                    // +-3% transaksi online gagal bayar & dibatalkan; sisanya berhasil
                    $rollStatus = mt_rand(1, 100);
                    if ($isOnline && $inFlightDay && $rollStatus <= 12) {
                        $paymentStatus = 'MENUNGGU';
                        $saleStatus = 'BARU';
                    } elseif ($isOnline && $rollStatus <= 5) {
                        $paymentStatus = 'GAGAL';
                        $saleStatus = 'BATAL';
                    } else {
                        $paymentStatus = 'BERHASIL';
                        if ($isOnline) {
                            $saleStatus = $inFlightDay ? (mt_rand(0, 1) ? 'DIPROSES' : 'DIKIRIM') : 'SELESAI';
                        } else {
                            $saleStatus = 'SELESAI';
                        }
                    }

                    $memberId = null;
                    if ($isOnline) {
                        $actorId = $activeCustomerIds[mt_rand(0, count($activeCustomerIds) - 1)];
                        $memberId = $actorId;
                    } else {
                        $actorId = $kasirIds[mt_rand(0, count($kasirIds) - 1)];
                        if (mt_rand(1, 100) <= 65) {
                            $memberId = $activeCustomerIds[mt_rand(0, count($activeCustomerIds) - 1)];
                        }
                    }

                    // Pilih 1-3 varian yang stoknya cukup (VAR0001 selalu disisakan untuk test)
                    $itemTarget = mt_rand(1, 3);
                    $chosenItems = [];
                    $attempts = 0;
                    while (count($chosenItems) < $itemTarget && $attempts < 20) {
                        $attempts++;
                        $varId = $weightedVariantPool[mt_rand(0, count($weightedVariantPool) - 1)];
                        if (isset($chosenItems[$varId])) {
                            continue;
                        }
                        $minKeep = ($varId === 'VAR0001') ? 5 : 0;
                        $avail = $variantStock[$varId] - $minKeep;
                        if ($avail < 1) {
                            continue;
                        }
                        $chosenItems[$varId] = min($avail, mt_rand(1, 2));
                    }

                    if (empty($chosenItems)) {
                        continue;
                    }

                    $saleId = 'PJ'.str_pad($pjNum++, 5, '0', STR_PAD_LEFT);
                    $subtotal = 0.0;
                    $lineBuffer = [];

                    foreach ($chosenItems as $varId => $qty) {
                        $meta = $variantMeta[$varId];
                        $prodId = $meta['id_produk'];
                        $normalPrice = $meta['harga_jual'];

                        // Promo aktif dengan diskon tertinggi pada saat transaksi
                        $bestPromo = null;
                        foreach ($promoLookupByProduct[$prodId] ?? [] as $candPromo) {
                            if ($saleMoment->betweenIncluded($candPromo['mulai'], $candPromo['selesai'])) {
                                if ($bestPromo === null || $candPromo['persen_diskon'] > $bestPromo['persen_diskon']) {
                                    $bestPromo = $candPromo;
                                }
                            }
                        }

                        $pct = $bestPromo ? $bestPromo['persen_diskon'] : 0.0;
                        $unitPrice = round($normalPrice * (1 - $pct / 100), 2);
                        $subtotal += $unitPrice * $qty;

                        $lineBuffer[] = [
                            'id_detail_penjualan' => 'DPJ'.str_pad($dpjNum++, 5, '0', STR_PAD_LEFT),
                            'id_penjualan' => $saleId,
                            'id_varian' => $varId,
                            'jumlah' => $qty,
                            'harga_normal' => $normalPrice,
                            'harga_satuan' => $unitPrice,
                            'persen_diskon' => $pct,
                            'id_promo' => $bestPromo['id_promo'] ?? null,
                        ];
                    }

                    $pointsEarned = 0;
                    $pointsUsed = 0;
                    $pointDiscount = 0.0;

                    if ($paymentStatus === 'BERHASIL' && $memberId !== null) {
                        $availBalance = $pointBalances[$memberId];
                        if ($availBalance >= 10 && mt_rand(1, 100) <= 25) {
                            $maxBySubtotal = (int) floor($subtotal / 500);
                            $pointsUsed = min($availBalance, $maxBySubtotal, mt_rand(5, 25));
                            $pointDiscount = $pointsUsed * 500;
                        }
                        $pointsEarned = (int) floor($subtotal / 10000);
                        $pointBalances[$memberId] += ($pointsEarned - $pointsUsed);
                    }

                    $nominalBayar = max(0, $subtotal - $pointDiscount);
                    $reference = $method === 'QR' ? "QR-{$saleId}-FORMA" : null;

                    $salesRows[] = [
                        'id_penjualan' => $saleId,
                        'id_akun' => $actorId,
                        'tanggal_penjualan' => $saleMoment,
                        'kanal_penjualan' => $channel,
                        'metode_pembayaran' => $method,
                        'status_pembayaran' => $paymentStatus,
                        'referensi_pembayaran' => $reference,
                        'nominal_bayar' => $nominalBayar,
                        'status_penjualan' => $saleStatus,
                        'alamat_pengiriman' => $isOnline ? $customerAddresses[$actorId] : null,
                        'poin_didapat' => $pointsEarned,
                        'poin_digunakan' => $pointsUsed,
                        'diskon_poin' => $pointDiscount,
                        'created_at' => $saleMoment,
                        'updated_at' => $saleMoment,
                    ];

                    if ($memberId !== null && !isset($firstTransaction[$memberId])) {
                        $firstTransaction[$memberId] = $saleMoment->copy();
                    }

                    if (!$isOnline && $memberId !== null) {
                        $salesMemberRows[] = [
                            'id_penjualan' => $saleId,
                            'id_akun_customer' => $memberId,
                        ];
                    }

                    foreach ($lineBuffer as $dRow) {
                        $salesDetailRows[] = $dRow;

                        if ($paymentStatus === 'BERHASIL') {
                            $varId = $dRow['id_varian'];
                            $variantStock[$varId] -= $dRow['jumlah'];

                            $mutasiRows[] = [
                                'id_varian' => $varId,
                                'tanggal' => $saleMoment,
                                'jenis' => 'KELUAR',
                                'jumlah' => $dRow['jumlah'],
                                'stok_sesudah' => $variantStock[$varId],
                                'referensi' => $saleId,
                                'created_at' => $saleMoment,
                                'updated_at' => $saleMoment,
                            ];
                        }
                    }
                }
            }

            // Beberapa varian sengaja dibuat stok rendah lewat stok opname (untuk alert stok)
            $opnameTime = $now->copy()->subMinute();
            foreach (['VAR0008', 'VAR0016', 'VAR0024'] as $idx => $lvId) {
                if (isset($variantStock[$lvId]) && $variantStock[$lvId] > 4) {
                    $diff = $variantStock[$lvId] - (2 + ($idx % 3));
                    $variantStock[$lvId] -= $diff;
                    $mutasiRows[] = [
                        'id_varian' => $lvId,
                        'tanggal' => $opnameTime,
                        'jenis' => 'PENYESUAIAN',
                        'jumlah' => -$diff,
                        'stok_sesudah' => $variantStock[$lvId],
                        'referensi' => 'OPNAME-AKHIR',
                        'created_at' => $opnameTime,
                        'updated_at' => $opnameTime,
                    ];
                }
            }

            foreach ($variantStock as $varId => $finalQty) {
                DB::table('varian_produk')
                    ->where('id_varian', $varId)
                    ->update(['stok' => $finalQty]);
            }

            // Customer tidak boleh berbelanja sebelum akunnya terdaftar
            foreach ($firstTransaction as $custId => $firstDate) {
                $registered = DB::table('akun')->where('id_akun', $custId)->value('created_at');
                if ($registered !== null && Carbon::parse($registered)->gt($firstDate)) {
                    $newReg = $firstDate->copy()->subHours(mt_rand(1, 72));
                    DB::table('akun')->where('id_akun', $custId)->update([
                        'created_at' => $newReg,
                        'updated_at' => $newReg,
                    ]);
                }
            }

            $this->insertChunked('pembelian_produk', $purchaseRows);
            $this->insertChunked('detail_pembelian', $purchaseDetailRows);
            $this->insertChunked('pengeluaran', $expenseRows);
            $this->insertChunked('penjualan', $salesRows);
            $this->insertChunked('penjualan_member', $salesMemberRows);
            $this->insertChunked('detail_penjualan', $salesDetailRows);
            $this->insertChunked('mutasi_stok', $mutasiRows);
        });
    }
}

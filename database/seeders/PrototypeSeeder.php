<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrototypeSeeder extends Seeder
{
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
            $now = now()->startOfMinute();
            $startPeriod = $now->copy()->subDays(360)->startOfDay();

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
            | 6. PROMO (10 Promo sepanjang 12 bulan + Promo aktif & mendatang)
            |--------------------------------------------------------------------------
            | Catatan: PRD001 sengaja hanya ikut PRM001 (10%) pada periode aktif $now
            | agar FinalSystemSmokeTest tetap deterministik.
            */
            $promoDefs = [
                ['PRM001', 'Everyday Essentials', 10, $now->copy()->subDays(12), $now->copy()->addDays(10), ['PRD001', 'PRD002', 'PRD006', 'PRD010', 'PRD013']],
                ['PRM002', 'Outerwear Week', 15, $now->copy()->subDays(7), $now->copy()->addDays(6), ['PRD004', 'PRD005', 'PRD007', 'PRD028', 'PRD029']],
                ['PRM003', 'Weekend Bottoms', 20, $now->copy()->subDays(30), $now->copy()->subDays(20), ['PRD008', 'PRD009', 'PRD024', 'PRD025']],
                ['PRM004', 'Year-Start Clearance', 15, $startPeriod->copy()->addDays(5), $startPeriod->copy()->addDays(35), ['PRD001', 'PRD003', 'PRD011', 'PRD014', 'PRD020']],
                ['PRM005', 'Ramadhan Gaya Bersih', 25, $startPeriod->copy()->addDays(70), $startPeriod->copy()->addDays(105), ['PRD006', 'PRD007', 'PRD020', 'PRD021', 'PRD022', 'PRD024']],
                ['PRM006', 'Mid-Year Casual Fest', 12, $startPeriod->copy()->addDays(145), $startPeriod->copy()->addDays(175), ['PRD002', 'PRD010', 'PRD012', 'PRD016', 'PRD027']],
                ['PRM007', 'Independence Sale 17%', 17, $startPeriod->copy()->addDays(215), $startPeriod->copy()->addDays(240), ['PRD001', 'PRD004', 'PRD015', 'PRD023', 'PRD030']],
                ['PRM008', 'Autumn Layering Deals', 18, $startPeriod->copy()->addDays(265), $startPeriod->copy()->addDays(295), ['PRD005', 'PRD017', 'PRD018', 'PRD028', 'PRD031']],
                ['PRM009', 'Twin-Date Flash Promo', 30, $startPeriod->copy()->addDays(310), $startPeriod->copy()->addDays(322), ['PRD003', 'PRD014', 'PRD019', 'PRD026', 'PRD032']],
                ['PRM010', 'Payday Collection', 12, $now->copy()->addDays(4), $now->copy()->addDays(12), ['PRD003', 'PRD008', 'PRD026']],
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
                    'updated_at' => $now,
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
            | 7. PEMBELIAN PRODUK + PENGELUARAN + MUTASI STOK (Restock 12 Bulan)
            |--------------------------------------------------------------------------
            | Setiap varian mendapat pembelian awal DITERIMA sebelum penjualan berjalan
            | + restock bulanan sehingga stok selalu positif dan HPP tertimbang akurat.
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

            // 7A. Restock awal untuk semua varian (6 PO di awal periode, seluruhnya DITERIMA)
            $variantChunks = array_chunk($variantIds, (int) ceil(count($variantIds) / 6));
            foreach ($variantChunks as $idx => $chunkVars) {
                $pbId = 'PB'.str_pad($pbNum++, 4, '0', STR_PAD_LEFT);
                $supId = $supplierIds[$idx % count($supplierIds)];
                $admId = $adminIds[$idx % count($adminIds)];
                $orderDate = $startPeriod->copy()->addDays($idx);
                $recvDate = $orderDate->copy()->addDays(2);
                $poTotal = 0;

                foreach ($chunkVars as $varId) {
                    $meta = $variantMeta[$varId];
                    $qty = 75 + ($meta['weight'] * 2); // stok awal cukup untuk 12 bulan
                    $cost = round($meta['harga_jual'] * (0.56 + (mt_rand(0, 6) / 100)), -2);

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
                        'tanggal' => $recvDate,
                        'jenis' => 'MASUK',
                        'jumlah' => $qty,
                        'stok_sesudah' => $variantStock[$varId],
                        'referensi' => $pbId,
                        'created_at' => $recvDate,
                        'updated_at' => $recvDate,
                    ];
                }

                $purchaseRows[] = [
                    'id_pembelian' => $pbId,
                    'id_supplier' => $supId,
                    'id_akun' => $admId,
                    'tanggal_pembelian' => $orderDate,
                    'tanggal_diterima' => $recvDate,
                    'status_pembelian' => 'DITERIMA',
                    'created_at' => $orderDate,
                    'updated_at' => $recvDate,
                ];

                $expenseRows[] = [
                    'id_pengeluaran' => 'PNG'.str_pad($pngNum++, 4, '0', STR_PAD_LEFT),
                    'id_akun' => $admId,
                    'id_pembelian' => $pbId,
                    'tanggal_pengeluaran' => $recvDate,
                    'jenis_pengeluaran' => 'PEMBELIAN_PRODUK',
                    'nominal' => $poTotal,
                    'keterangan' => "Pembelian awal {$pbId} diterima",
                    'created_at' => $recvDate,
                    'updated_at' => $recvDate,
                ];
            }

            // 7B. Restock berkala per bulan (bulan 1..12) + pengeluaran operasional bulanan
            $restockSchedule = [];
            for ($m = 0; $m < 12; $m++) {
                $monthStart = $startPeriod->copy()->addDays($m * 30);

                // Pengeluaran operasional rutin tiap bulan
                $opsItems = [
                    ['SEWA', 2500000, 3, 'Sewa ruko & gudang bulanan'],
                    ['LISTRIK', mt_rand(320000, 480000), 10, 'Tagihan listrik toko'],
                    ['INTERNET', 350000, 12, 'Internet & Wi-Fi kasir'],
                    ['ONGKIR', mt_rand(150000, 310000), 18, 'Biaya ekspedisi & pengambilan stok'],
                    ['PERAWATAN', mt_rand(100000, 250000), 22, 'Perawatan toko & display'],
                ];
                if ($m % 2 === 1) {
                    $opsItems[] = ['LAINNYA', mt_rand(120000, 260000), 25, 'Perlengkapan kemasan & label'];
                }

                foreach ($opsItems as [$jenis, $nominal, $dayOff, $ket]) {
                    $expDate = $monthStart->copy()->addDays(min(28, $dayOff));
                    if ($expDate->gt($now)) {
                        $expDate = $now->copy()->subHours(mt_rand(2, 36));
                    }
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

                // 5-6 PO restock per bulan (beberapa DIPESAN / BATAL di akhir)
                $poInMonth = 6;
                for ($p = 0; $p < $poInMonth; $p++) {
                    $pbId = 'PB'.str_pad($pbNum++, 4, '0', STR_PAD_LEFT);
                    $orderDate = $monthStart->copy()->addDays(2 + $p * 4);
                    if ($orderDate->gt($now)) {
                        $orderDate = $now->copy()->subDays(1);
                    }

                    $status = 'DITERIMA';
                    if ($m === 11 && $p === $poInMonth - 1) {
                        $status = 'DIPESAN';
                    } elseif ($p === $poInMonth - 1 && $m % 3 === 0) {
                        $status = 'BATAL';
                    }

                    $recvDate = $status === 'DITERIMA' ? $orderDate->copy()->addDays(2) : null;
                    if ($recvDate && $recvDate->gt($now)) {
                        $recvDate = $now->copy()->subHours(12);
                    }

                    $supId = $supplierIds[($m + $p) % count($supplierIds)];
                    $admId = $adminIds[($m + $p) % count($adminIds)];

                    $pickedVars = [];
                    $itemCount = mt_rand(4, 7);
                    while (count($pickedVars) < $itemCount) {
                        $cand = $variantIds[mt_rand(0, count($variantIds) - 1)];
                        $pickedVars[$cand] = true;
                    }

                    $poTotal = 0;
                    $poDetails = [];
                    foreach (array_keys($pickedVars) as $varId) {
                        $meta = $variantMeta[$varId];
                        $qty = mt_rand(8, 22);
                        $cost = round($meta['harga_jual'] * (0.55 + (mt_rand(0, 8) / 100)), -2);

                        $purchaseDetailRows[] = [
                            'id_detail_pembelian' => 'DPB'.str_pad($dpbNum++, 5, '0', STR_PAD_LEFT),
                            'id_pembelian' => $pbId,
                            'id_varian' => $varId,
                            'jumlah' => $qty,
                            'harga_satuan' => $cost,
                        ];

                        if ($status === 'DITERIMA') {
                            $poDetails[$varId] = $qty;
                            $poTotal += $qty * $cost;
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
                        $restockSchedule[] = [
                            'pb_id' => $pbId,
                            'recv_date' => $recvDate,
                            'adm_id' => $admId,
                            'total' => $poTotal,
                            'items' => $poDetails,
                        ];
                    }
                }
            }

            $this->insertChunked('pembelian_produk', $purchaseRows);
            $this->insertChunked('detail_pembelian', $purchaseDetailRows);

            /*
            |--------------------------------------------------------------------------
            | 8. SIMULASI KRONOLOGIS PENJUALAN (2.200+ transaksi sepanjang 12 bulan)
            |--------------------------------------------------------------------------
            */
            // Bangun pool berbobot berdasarkan bobot ukuran (M/L lebih sering terpilih)
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
            $kasirIds = ['KSR001', 'KSR002', 'KSR004'];

            $pjNum = 1;
            $dpjNum = 1;

            // Urutkan jadwal restock lanjutan berdasarkan tanggal diterima
            usort($restockSchedule, fn ($a, $b) => $a['recv_date']->timestamp <=> $b['recv_date']->timestamp);
            $restockIdx = 0;
            $restockCount = count($restockSchedule);

            // Iterasi harian selama 360 hari dari $startPeriod sampai $now
            for ($day = 0; $day <= 360; $day++) {
                $currentDate = $startPeriod->copy()->addDays($day);
                if ($currentDate->gt($now)) {
                    break;
                }

                // Terapkan restock yang sudah diterima pada atau sebelum hari ini
                while ($restockIdx < $restockCount && $restockSchedule[$restockIdx]['recv_date']->lte($currentDate->copy()->endOfDay())) {
                    $rs = $restockSchedule[$restockIdx];
                    foreach ($rs['items'] as $varId => $qty) {
                        $variantStock[$varId] += $qty;
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

                    $restockIdx++;
                }

                // Pola musiman & akhir pekan: base 5 trx/hari, weekend +3, bulan peak +3
                $isWeekend = in_array($currentDate->dayOfWeekIso, [6, 7], true);
                $isSeasonalPeak = ($day >= 70 && $day <= 105) || ($day >= 315);
                $txToday = mt_rand(4, 6) + ($isWeekend ? 3 : 0) + ($isSeasonalPeak ? 2 : 0);

                for ($t = 0; $t < $txToday; $t++) {
                    $saleMoment = $currentDate->copy()->setTime(mt_rand(9, 21), mt_rand(0, 59), mt_rand(0, 59));
                    if ($saleMoment->gt($now)) {
                        $saleMoment = $now->copy()->subMinutes(mt_rand(15, 180));
                    }

                    $isOnline = mt_rand(1, 100) <= 45;
                    $channel = $isOnline ? 'ONLINE' : 'OFFLINE';
                    $method = $isOnline ? 'QR' : (mt_rand(1, 100) <= 55 ? 'TUNAI' : 'QR');

                    // Sekitar 4% transaksi berstatus GAGAL/BATAL atau MENUNGGU (untuk uji filter ETL)
                    $rollStatus = mt_rand(1, 100);
                    if ($isOnline && $day >= 358 && $rollStatus <= 4) {
                        $paymentStatus = 'MENUNGGU';
                        $saleStatus = 'BARU';
                    } elseif ($rollStatus <= 3) {
                        $paymentStatus = 'GAGAL';
                        $saleStatus = 'BATAL';
                    } else {
                        $paymentStatus = 'BERHASIL';
                        if ($isOnline) {
                            $saleStatus = $day >= 358 ? (mt_rand(0, 1) ? 'DIPROSES' : 'DIKIRIM') : 'SELESAI';
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

                    // Pilih 1-3 varian yang stoknya masih cukup (sisakan buffer 2 agar beberapa varian pas untuk test smoke)
                    $itemTarget = mt_rand(1, 3);
                    $chosenItems = [];
                    $attempts = 0;
                    while (count($chosenItems) < $itemTarget && $attempts < 20) {
                        $attempts++;
                        $varId = $weightedVariantPool[mt_rand(0, count($weightedVariantPool) - 1)];
                        if (isset($chosenItems[$varId])) {
                            continue;
                        }
                        // Sisakan minimal 2 stok di akhir periode untuk VAR0001 agar test smoke aman
                        $minKeep = ($varId === 'VAR0001') ? 5 : 2;
                        $avail = $variantStock[$varId] - $minKeep;
                        if ($avail < 1) {
                            continue;
                        }
                        $qty = min($avail, mt_rand(1, 2));
                        $chosenItems[$varId] = $qty;
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

                        // Cari promo aktif tertinggi pada $saleMoment
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

            // Pastikan sisa restock (jika ada) juga diterapkan
            while ($restockIdx < $restockCount) {
                $rs = $restockSchedule[$restockIdx++];
                foreach ($rs['items'] as $varId => $qty) {
                    $variantStock[$varId] += $qty;
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
            }

            // Pastikan beberapa varian sengaja berada di kondisi low stock (<= stok_minimum) untuk alert dashboard
            $lowStockVariants = ['VAR0008', 'VAR0016', 'VAR0024'];
            foreach ($lowStockVariants as $idx => $lvId) {
                if (isset($variantStock[$lvId]) && $variantStock[$lvId] > 4) {
                    $diff = $variantStock[$lvId] - (2 + ($idx % 3));
                    $variantStock[$lvId] -= $diff;
                    $mutasiRows[] = [
                        'id_varian' => $lvId,
                        'tanggal' => $now->copy()->subHours(2),
                        'jenis' => 'PENYESUAIAN',
                        'jumlah' => -$diff,
                        'stok_sesudah' => $variantStock[$lvId],
                        'referensi' => 'OPNAME-AWAL',
                        'created_at' => $now->copy()->subHours(2),
                        'updated_at' => $now->copy()->subHours(2),
                    ];
                }
            }

            // Update stok akhir setiap varian di varian_produk
            foreach ($variantStock as $varId => $finalQty) {
                DB::table('varian_produk')
                    ->where('id_varian', $varId)
                    ->update(['stok' => max(0, $finalQty)]);
            }

            $this->insertChunked('pengeluaran', $expenseRows);
            $this->insertChunked('penjualan', $salesRows);
            $this->insertChunked('penjualan_member', $salesMemberRows);
            $this->insertChunked('detail_penjualan', $salesDetailRows);
            $this->insertChunked('mutasi_stok', $mutasiRows);
        });
    }
}

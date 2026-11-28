<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_store_sales_and_purchases_follow_the_date_filter(): void
    {
        $bambang = Pemilik::query()->where('kode', '001')->firstOrFail();
        $ani = Pemilik::query()->create(['kode' => '002', 'nama' => 'Ani']);

        $bambangProduk = Produk::query()->create([
            'pemilik_id' => $bambang->id,
            'nama' => 'Obat Bambang',
            'satuan' => 'pcs',
        ]);
        $aniProduk = Produk::query()->create([
            'pemilik_id' => $ani->id,
            'nama' => 'Obat Ani',
            'satuan' => 'pcs',
        ]);

        $today = now()->toDateString();
        $this->createSale($bambangProduk, $today, 100, 150);
        $this->createPurchase($bambangProduk, $today, 40);
        $this->createSale($aniProduk, $today, 200, 300);
        $this->createPurchase($aniProduk, $today, 50);

        // Transaksi bulan lalu tidak masuk dalam rentang bawaan (bulan ini).
        $lastMonth = now()->subMonth()->toDateString();
        $this->createSale($bambangProduk, $lastMonth, 500, 700);

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Ringkasan Omset per Toko')
            ->assertSee('id="omset-toko-search"', false)
            ->assertSee('TOTAL SEMUA TOKO')
            ->assertDontSee('Total (Penjualan - Pembelian)');

        $this->assertSame(450.0, $response->viewData('totalSales'));
        $this->assertSame(90.0, $response->viewData('totalPurchases'));

        $stores = collect($response->viewData('storeSummaries'))->keyBy('nama');
        $this->assertSame(150.0, $stores['Bambang']['sales']);
        $this->assertSame(40.0, $stores['Bambang']['purchases']);
        $this->assertSame(300.0, $stores['Ani']['sales']);
        $this->assertSame(50.0, $stores['Ani']['purchases']);

        $filtered = $this->get(route('dashboard', [
            'tanggal_mulai' => now()->subMonth()->startOfMonth()->toDateString(),
            'tanggal_selesai' => $today,
        ]));

        $filtered->assertOk()
            ->assertSee('Rp 1.150')
            ->assertSee('value="'.now()->subMonth()->startOfMonth()->toDateString().'"', false);

        $this->assertSame(1150.0, $filtered->viewData('totalSales'));
        $this->assertSame(90.0, $filtered->viewData('totalPurchases'));

        $filteredStores = collect($filtered->viewData('storeSummaries'))->keyBy('nama');
        $this->assertSame(850.0, $filteredStores['Bambang']['sales']);
    }

    public function test_dashboard_lists_remaining_stock_and_its_value(): void
    {
        $bambang = Pemilik::query()->where('kode', '001')->firstOrFail();

        $produkSisa = Produk::query()->create([
            'pemilik_id' => $bambang->id,
            'nama' => 'Obat Sisa',
            'satuan' => 'pcs',
        ]);
        $produkSisa->stok = '3.500';
        $produkSisa->harga_beli = '1000';
        $produkSisa->save();

        $produkHabis = Produk::query()->create([
            'pemilik_id' => $bambang->id,
            'nama' => 'Obat Habis',
            'satuan' => 'pcs',
        ]);
        $produkHabis->stok = '0';
        $produkHabis->harga_beli = '500';
        $produkHabis->save();

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Stok Akhir (Barang Belum Terjual)')
            ->assertSee('id="stok-akhir-search"', false)
            ->assertSee('Nilai Stok Akhir')
            ->assertSee('Obat Sisa')
            ->assertDontSee('Obat Habis');

        $this->assertSame(3500.0, $response->viewData('totalNilaiStok'));
        $this->assertSame(1, $response->viewData('totalItemStok'));

        $stok = collect($response->viewData('stokAkhirs'))->first();
        $this->assertSame('Obat Sisa', $stok['nama']);
        $this->assertSame(3.5, $stok['stok']);
        $this->assertSame(3500.0, $stok['nilai']);
    }

    public function test_stock_as_of_a_past_date_uses_transactions_after_that_date(): void
    {
        $bambang = Pemilik::query()->where('kode', '001')->firstOrFail();

        $produk = Produk::query()->create([
            'pemilik_id' => $bambang->id,
            'nama' => 'Obat Historis',
            'satuan' => 'pcs',
        ]);
        $produk->stok = '10';
        $produk->harga_beli = '1000';
        $produk->save();

        $endDate = now()->subDays(5)->toDateString();
        $afterEndDate = now()->subDay()->toDateString();

        // Stok sekarang 10 berasal dari kondisi setelah transaksi di bawah.
        $this->createSale($produk, $afterEndDate, 1000, 1500, 2);
        $this->createPurchase($produk, $afterEndDate, 1000, 3);

        $response = $this->get(route('dashboard', [
            'tanggal_mulai' => now()->subDays(10)->toDateString(),
            'tanggal_selesai' => $endDate,
        ]));

        $response->assertOk();

        $stok = collect($response->viewData('stokAkhirs'))->firstWhere('nama', 'Obat Historis');

        $this->assertNotNull($stok);
        // 10 stok sekarang + 2 penjualan setelah tanggal - 3 pembelian setelah tanggal.
        $this->assertSame(9.0, $stok['stok']);
        $this->assertSame(9000.0, $stok['nilai']);
    }

    private function createSale(Produk $produk, string $tanggal, float $cost, float $price, float $quantity = 1): void
    {
        Penjualan::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => $tanggal,
            'satuan' => 'pcs',
            'jumlah' => (string) $quantity,
            'harga_beli_satuan' => (string) $cost,
            'harga_jual' => (string) $price,
            'total_harga_beli' => (string) ($cost * $quantity),
            'total_harga_jual' => (string) ($price * $quantity),
            'laba' => (string) (($price - $cost) * $quantity),
        ]);
    }

    private function createPurchase(Produk $produk, string $tanggal, float $cost, float $quantity = 1): void
    {
        Pembelian::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => $tanggal,
            'satuan' => 'pcs',
            'jumlah' => (string) $quantity,
            'harga_beli' => (string) $cost,
            'total_harga' => (string) ($cost * $quantity),
        ]);
    }
}

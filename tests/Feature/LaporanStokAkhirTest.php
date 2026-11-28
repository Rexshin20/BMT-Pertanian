<?php

namespace Tests\Feature;

use App\Models\Pemilik;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LaporanStokAkhirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_laporan_stok_akhir(): void
    {
        $lastCode = (int) Pemilik::query()
            ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
            ->value('kode_terakhir');
        $kode = str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT);

        $pemilik = Pemilik::query()->create(['kode' => $kode, 'nama' => 'Budi']);
        $produk = Produk::query()->create([
            'nama' => 'Kopi',
            'pemilik_id' => $pemilik->id,
            'satuan' => 'pcs',
            'harga_jual' => 1000,
        ]);

        // Stok awal 10 pcs (pembelian tidak ikut dikirim ke laporan, sesuai permintaan).
        $produk->increment('stok', 10);

        // Penjualan 3 pcs pada 5 Okt (masuk periode) dan 2 pcs pada 15 Okt (setelah periode).
        $this->createPenjualan($produk, '2026-10-05', 3);
        $this->createPenjualan($produk, '2026-10-15', 2);
        $this->assertSame('5.000', $produk->fresh()->stok, 'stok sekarang harus 5');

        $response = $this->get(route('laporan.stok-akhir', [
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-10',
        ]));

        $response->assertOk()
            ->assertSee('Stok Awal')
            ->assertSee('Penjualan')
            ->assertSee('Stok Akhir')
            ->assertSee('Kopi');

        $viewData = $response->viewData('laporans');
        $this->assertCount(1, $viewData);

        $laporan = $viewData->first();
        $this->assertSame('10', rtrim(rtrim(number_format((float) $laporan->stok_awal, 3, ',', '.'), '0'), ','), 'stok awal 1 Okt = 10');
        $this->assertSame('3', rtrim(rtrim(number_format((float) $laporan->penjualan, 3, ',', '.'), '0'), ','), 'penjualan 1-10 Okt = 3');
        $this->assertSame('7', rtrim(rtrim(number_format((float) $laporan->stok_akhir, 3, ',', '.'), '0'), ','), 'stok akhir 10 Okt = 10 - 3 = 7');

        // Filter pemilik ikut berfungsi.
        $this->get(route('laporan.stok-akhir', ['pemilik' => $kode]))
            ->assertOk()
            ->assertSee('Kopi');

        $this->get(route('laporan.stok-akhir', ['pemilik' => str_pad((string) ($lastCode + 999), 3, '0', STR_PAD_LEFT)]))
            ->assertStatus(302);
    }

    public function test_laporan_stok_akhir_defaults_to_all_transaction_dates(): void
    {
        $lastCode = (int) Pemilik::query()
            ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
            ->value('kode_terakhir');
        $kode = str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT);

        $pemilik = Pemilik::query()->create(['kode' => $kode, 'nama' => 'Budi']);
        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $kode,
            'nama_barang' => 'Kopi',
            'tanggal' => '2026-07-15',
            'satuan' => 'pcs',
            'jumlah' => '10',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $produk = Produk::query()->where('pemilik_id', $pemilik->id)->firstOrFail();
        $this->createPenjualan($produk, '2026-08-05', 2);
        $this->createPenjualan($produk, '2026-10-05', 3);
        $this->assertSame('5.000', $produk->fresh()->stok);

        $response = $this->get(route('laporan.stok-akhir'));
        $response->assertOk();

        $this->assertSame('2026-07-15', $response->viewData('tanggalMulai'));
        $this->assertSame(Carbon::today()->toDateString(), $response->viewData('tanggalSelesai'));

        $laporan = collect($response->viewData('laporans'))->firstWhere('nama_barang', 'Kopi');
        $this->assertNotNull($laporan);
        $this->assertSame('10', rtrim(rtrim(number_format((float) $laporan->stok_awal, 3, ',', '.'), '0'), ','));
        $this->assertSame('5', rtrim(rtrim(number_format((float) $laporan->penjualan, 3, ',', '.'), '0'), ','));
        $this->assertSame('5', rtrim(rtrim(number_format((float) $laporan->stok_akhir, 3, ',', '.'), '0'), ','));
    }

    public function test_stok_akhir_can_delete_product_and_related_transactions(): void
    {
        $lastCode = (int) Pemilik::query()
            ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
            ->value('kode_terakhir');
        $kode = str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT);

        $pemilik = Pemilik::query()->create(['kode' => $kode, 'nama' => 'Budi']);
        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $kode,
            'nama_barang' => 'Kopi',
            'tanggal' => '2026-10-01',
            'satuan' => 'pcs',
            'jumlah' => '10',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $produk = Produk::query()->where('pemilik_id', $pemilik->id)->firstOrFail();
        $this->createPenjualan($produk, '2026-10-05', 2);

        $url = route('laporan.stok-akhir', ['pemilik' => $kode]);
        $this->get($url)
            ->assertOk()
            ->assertSee('Hapus')
            ->assertSee(route('laporan.stok-akhir.destroy', $produk));

        $this->from($url)
            ->delete(route('laporan.stok-akhir.destroy', $produk))
            ->assertRedirect($url)
            ->assertSessionHas('success', 'Barang dan seluruh transaksi terkait berhasil dihapus.');

        $this->assertDatabaseMissing('produks', ['id' => $produk->id]);
        $this->assertDatabaseMissing('pembelians', ['produk_id' => $produk->id]);
        $this->assertDatabaseMissing('penjuwalans', ['produk_id' => $produk->id]);
    }

    public function test_berat_bersih_diambil_dari_satuan_bila_nama_barang_tidak_memuat_ukuran(): void
    {
        $lastCode = (int) Pemilik::query()
            ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
            ->value('kode_terakhir');
        $kode = str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT);

        $pemilik = Pemilik::query()->create(['kode' => $kode, 'nama' => 'Pemilik ukuran']);

        $berukuran = Produk::query()->create([
            'nama' => 'gramason',
            'pemilik_id' => $pemilik->id,
            'satuan' => '200 ml',
            'harga_jual' => 60000,
        ]);
        $berukuran->increment('stok', 5);
        $this->createPenjualan($berukuran, '2026-10-05', 3);

        $tanpaUkuran = Produk::query()->create([
            'nama' => 'Cangkul',
            'pemilik_id' => $pemilik->id,
            'satuan' => 'pcs',
            'harga_jual' => 50000,
        ]);
        $tanpaUkuran->increment('stok', 2);

        $response = $this->get(route('laporan.stok-akhir', [
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-31',
            'pemilik' => $kode,
        ]));

        $response->assertOk()
            ->assertSee('<td class="text-center">200 ml</td>', false)
            ->assertDontSee('<td class="text-center">pcs</td>', false);

        $laporan = collect($response->viewData('laporans'))->keyBy('nama_barang');
        $this->assertSame('200 ml', $laporan['gramason']->berat_bersih);
        $this->assertSame('', $laporan['Cangkul']->berat_bersih);
    }

    private function createPenjualan(Produk $produk, string $tanggal, float $jumlah): void
    {
        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $produk->pemilik->kode,
            'produk_id' => $produk->id,
            'tanggal' => $tanggal,
            'satuan' => $produk->satuan,
            'jumlah' => (string) $jumlah,
            'harga_jual' => '1000',
        ]);
    }
}

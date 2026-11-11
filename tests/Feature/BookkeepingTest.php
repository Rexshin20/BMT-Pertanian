<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BookkeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create();
        $this->actingAs($this->testUser);
    }

    public function test_create_transaction_pages_show_tables_with_add_buttons(): void
    {
        $pemilik = $this->createPemilik('Ani');
        $produk = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Beras',
            'satuan' => 'kg',
        ]);
        $produk->stok = '12.500';
        $produk->save();

        $this->get(route('penjualan.create.list'))
            ->assertOk()
            ->assertSee('Create Penjualan')
            ->assertSee('Riwayat Penjualan')
            ->assertSee('href="'.route('penjualan.history').'"', false)
            ->assertSee('Tambah Penjualan')
            ->assertDontSee('position-fixed')
            ->assertDontSee('transaction-history-modal')
            ->assertSee('action="'.route('penjualan.store').'"', false)
            ->assertSee('name="harga_jual"', false)
            ->assertSee('Simpan Penjualan')
            ->assertDontSee('Memuat formulir')
            ->assertDontSee('fetch(');

        $this->get(route('pembelian.create.list'))
            ->assertOk()
            ->assertSee('Create Pembelian')
            ->assertSee('Riwayat Pembelian')
            ->assertSee('href="'.route('pembelian.history').'"', false)
            ->assertSee('Tambah Pembelian')
            ->assertDontSee('position-fixed')
            ->assertDontSee('transaction-history-modal')
            ->assertSee('action="'.route('pembelian.store').'"', false)
            ->assertSee('name="harga_beli"', false)
            ->assertSee('Simpan Pembelian')
            ->assertDontSee('Memuat formulir')
            ->assertDontSee('fetch(');

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Beras',
            'tanggal' => '2026-10-10',
            'satuan' => 'kg',
            'jumlah' => '1',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));
        $pembelian = Pembelian::query()->firstOrFail();

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $pemilik->kode,
            'produk_id' => $produk->id,
            'tanggal' => '2026-10-11',
            'satuan' => 'kg',
            'jumlah' => '1',
            'harga_jual' => '150',
        ])->assertRedirect(route('penjualan.create.list'));
        $penjualan = Penjualan::query()->firstOrFail();

        $this->get(route('pembelian.history'))
            ->assertSee('Beras')
            ->assertSee(route('pembelian.edit', $pembelian));
        $this->get(route('penjualan.history'))
            ->assertSee('Beras')
            ->assertSee(route('penjualan.edit', $penjualan));
        $this->get(route('penjualan.index'))
            ->assertDontSee('Tambah Penjualan')
            ->assertDontSee(route('produk.edit', $produk))
            ->assertDontSee(route('penjualan.edit', $penjualan));
        $this->get(route('pembelian.index'))
            ->assertDontSee('Tambah Pembelian')
            ->assertDontSee(route('pembelian.edit', $pembelian));
    }

    public function test_products_can_be_created_and_purchases_increase_stock(): void
    {
        $pemilik = $this->createPemilik('Ani');

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Kopi',
            'tanggal' => '2026-10-16',
            'satuan' => 'per bungkus',
            'jumlah' => '10',
            'harga_beli' => '8250',
        ])->assertRedirect(route('pembelian.create.list'))
            ->assertSessionHas('success', 'Pembelian dicatat dan stok barang bertambah.');

        $produk = Produk::query()->where('nama', 'Kopi')->where('pemilik_id', $pemilik->id)->firstOrFail();
        $this->assertSame('10.000', $produk->fresh()->stok);
        $this->assertSame('8250.00', $produk->fresh()->harga_beli);
        $this->assertDatabaseHas('pembelians', [
            'produk_id' => $produk->id,
            'satuan' => 'per bungkus',
            'jumlah' => '10.000',
            'total_harga' => '82500.00',
        ]);
        $this->assertSame($pemilik->id, $produk->pemilik_id);

        $this->get(route('pembelian.master-data'))
            ->assertOk()
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Nama Barang')
            ->assertSee('Unit')
            ->assertSee('Harga')
            ->assertSee('Total')
            ->assertSee('per bungkus')
            ->assertSee('>10</td>', false)
            ->assertSee('Rp 82.500,00')
            ->assertDontSee('>10,000</td>', false);
    }

    public function test_purchases_with_the_same_name_but_different_units_create_separate_products(): void
    {
        $pemilik = $this->createPemilik('Bambang');

        foreach ([['200 ml', '2000'], ['500 ml', '3000']] as [$satuan, $harga]) {
            $this->post(route('pembelian.store'), [
                'pemilik_kode' => $pemilik->kode,
                'nama_barang' => 'Kaka',
                'tanggal' => '2026-11-11',
                'satuan' => $satuan,
                'jumlah' => '2',
                'harga_beli' => $harga,
            ])->assertRedirect(route('pembelian.create.list'));
        }

        $this->assertSame(2, Produk::query()->where('nama', 'Kaka')->count());

        $kecil = Produk::query()->where('nama', 'Kaka')->where('satuan', '200 ml')->firstOrFail();
        $besar = Produk::query()->where('nama', 'Kaka')->where('satuan', '500 ml')->firstOrFail();

        $this->assertSame('2.000', $kecil->stok);
        $this->assertSame('2.000', $besar->stok);
        $this->assertSame('2000.00', $kecil->harga_beli);
        $this->assertSame('3000.00', $besar->harga_beli);

        $this->get(route('penjualan.create'))
            ->assertOk()
            ->assertSee('Kaka — stok 2 200 ml')
            ->assertSee('Kaka — stok 2 500 ml');

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $pemilik->kode,
            'produk_id' => $besar->id,
            'tanggal' => '2026-11-11',
            'satuan' => '500 ml',
            'jumlah' => '1',
            'harga_jual' => '4000',
        ])->assertRedirect(route('penjualan.create.list'));

        $this->assertSame('2.000', $kecil->fresh()->stok);
        $this->assertSame('1.000', $besar->fresh()->stok);
    }

    public function test_all_names_sales_view_merges_rows_with_the_same_date_item_unit_and_price(): void
    {
        $andi = $this->createPemilik('Andi');
        $budi = $this->createPemilik('Budi');
        $kopiAndi = Produk::query()->create(['pemilik_id' => $andi->id, 'nama' => 'Kopi', 'satuan' => 'pcs']);
        $kopiBudi = Produk::query()->create(['pemilik_id' => $budi->id, 'nama' => 'Kopi', 'satuan' => 'pcs']);
        $kopiBotol = Produk::query()->create(['pemilik_id' => $andi->id, 'nama' => 'Kopi', 'satuan' => 'botol']);

        $rows = [
            [$kopiAndi, 'pcs', '2', '10000'],
            [$kopiBudi, 'pcs', '3', '10000'],
            [$kopiAndi, 'pcs', '1', '12000'],
            [$kopiBotol, 'botol', '1', '10000'],
        ];

        foreach ($rows as [$produk, $satuan, $jumlah, $harga]) {
            $total = (float) $jumlah * (float) $harga;

            Penjualan::query()->create([
                'produk_id' => $produk->id,
                'tanggal' => '2026-11-11',
                'satuan' => $satuan,
                'jumlah' => $jumlah,
                'harga_beli_satuan' => '1000',
                'harga_jual' => $harga,
                'total_harga_beli' => (string) ((float) $jumlah * 1000),
                'total_harga_jual' => (string) $total,
                'laba' => (string) ($total - (float) $jumlah * 1000),
            ]);
        }

        $this->get(route('penjualan.index'))
            ->assertOk()
            ->assertSee('Rp 50.000,00')
            ->assertSee('Rp 12.000,00')
            ->assertSee('Rp 10.000,00')
            ->assertDontSee('Rp 20.000,00')
            ->assertDontSee('Rp 30.000,00')
            ->assertSee('>5</td>', false);

        $this->get(route('penjualan.index', ['pemilik' => $andi->kode]))
            ->assertOk()
            ->assertSee('Rp 20.000,00')
            ->assertSee('Rp 12.000,00')
            ->assertSee('Rp 10.000,00')
            ->assertDontSee('Rp 50.000,00');
    }

    public function test_all_names_purchases_view_merges_rows_with_the_same_date_item_unit_and_price(): void
    {
        $andi = $this->createPemilik('Andi');
        $budi = $this->createPemilik('Budi');
        $berasAndi = Produk::query()->create(['pemilik_id' => $andi->id, 'nama' => 'Beras', 'satuan' => 'kg']);
        $berasBudi = Produk::query()->create(['pemilik_id' => $budi->id, 'nama' => 'Beras', 'satuan' => 'kg']);
        $berasKarung = Produk::query()->create(['pemilik_id' => $andi->id, 'nama' => 'Beras', 'satuan' => 'karung']);

        $rows = [
            [$berasAndi, 'kg', '2', '2000'],
            [$berasBudi, 'kg', '3', '2000'],
            [$berasAndi, 'kg', '1', '3000'],
            [$berasKarung, 'karung', '1', '2000'],
        ];

        foreach ($rows as [$produk, $satuan, $jumlah, $harga]) {
            Pembelian::query()->create([
                'produk_id' => $produk->id,
                'tanggal' => '2026-11-11',
                'satuan' => $satuan,
                'jumlah' => $jumlah,
                'harga_beli' => $harga,
                'total_harga' => (string) ((float) $jumlah * (float) $harga),
            ]);
        }

        $this->get(route('pembelian.index'))
            ->assertOk()
            ->assertSee('Rp 10.000,00')
            ->assertSee('Rp 3.000,00')
            ->assertSee('Rp 2.000,00')
            ->assertDontSee('Rp 4.000,00')
            ->assertDontSee('Rp 6.000,00')
            ->assertSee('>5</td>', false);

        $this->get(route('pembelian.index', ['pemilik' => $andi->kode]))
            ->assertOk()
            ->assertSee('Rp 4.000,00')
            ->assertSee('Rp 3.000,00')
            ->assertSee('Rp 2.000,00')
            ->assertDontSee('Rp 10.000,00');
    }

    public function test_sales_reduce_stock_and_keep_the_purchase_cost_snapshot_for_profit_reports(): void
    {
        $pemilik = $this->createPemilik('Budi');
        $produk = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Teh',
            'satuan' => 'kotak',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Teh',
            'tanggal' => '2026-10-01',
            'satuan' => 'per kotak',
            'jumlah' => '10',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $pemilik->kode,
            'produk_id' => $produk->id,
            'tanggal' => '2026-10-10',
            'satuan' => 'per kotak',
            'jumlah' => '2.5',
            'harga_jual' => '150',
        ])->assertRedirect(route('penjualan.create.list'))
            ->assertSessionHas('success', 'Penjualan dicatat dan stok barang berkurang.');

        $this->assertSame('7.500', $produk->fresh()->stok);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Teh',
            'tanggal' => '2026-10-12',
            'satuan' => 'per kotak',
            'jumlah' => '5',
            'harga_beli' => '120',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->assertSame('100.00', Penjualan::query()->firstOrFail()->harga_beli_satuan);

        $this->get(route('penjualan.master-data', [
            'q' => 'Teh',
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-10',
        ]))
            ->assertOk()
            ->assertSee('No.')
            ->assertSee('Unit')
            ->assertSee('Harga')
            ->assertSee('Total')
            ->assertSee('Rp 150,00')
            ->assertSee('Rp 375,00')
            ->assertSee('Budi')
            ->assertSee('per kotak')
            ->assertSee('>2,5</td>', false);

        $this->get(route('laporan.laba-rugi', [
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-31',
            'pemilik' => $pemilik->kode,
        ]))
            ->assertOk()
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Nama Barang')
            ->assertSee('Unit')
            ->assertSee('Harga Beli')
            ->assertSee('Total Beli')
            ->assertSee('Harga Jual')
            ->assertSee('Total Jual')
            ->assertSee('Rp 100,00')
            ->assertSee('Rp 250,00')
            ->assertSee('Rp 150,00')
            ->assertSee('Rp 375,00')
            ->assertSee('Rp 125,00')
            ->assertSee($pemilik->kode)
            ->assertSee('Budi');
    }

    public function test_purchase_transactions_can_be_edited_and_adjust_stock(): void
    {
        $pemilik = $this->createPemilik('Ani');
        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Beras',
            'tanggal' => '2026-10-01',
            'satuan' => 'kg',
            'jumlah' => '10',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $produk = Produk::query()->where('pemilik_id', $pemilik->id)->firstOrFail();
        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $pemilik->kode,
            'produk_id' => $produk->id,
            'tanggal' => '2026-10-02',
            'satuan' => 'kg',
            'jumlah' => '3',
            'harga_jual' => '150',
        ])->assertRedirect(route('penjualan.create.list'));

        $pembelian = Pembelian::query()->firstOrFail();
        $this->get(route('pembelian.edit', $pembelian))
            ->assertOk()
            ->assertSee('name="produk_id"', false)
            ->assertSee('name="satuan"', false)
            ->assertSee('name="tanggal"', false)
            ->assertSee('name="jumlah"', false)
            ->assertSee('name="harga_beli"', false);

        $this->put(route('pembelian.update', $pembelian), [
            'produk_id' => $produk->id,
            'satuan' => 'kg',
            'tanggal' => '2026-10-03',
            'jumlah' => '8',
            'harga_beli' => '120',
        ])->assertRedirect(route('pembelian.create.list'))
            ->assertSessionHas('success', 'Transaksi pembelian berhasil diperbarui dan stok disesuaikan.');

        $this->assertSame('5.000', $produk->fresh()->stok);
        $this->assertSame('120.00', $produk->fresh()->harga_beli);
        $this->assertDatabaseHas('pembelians', [
            'id' => $pembelian->id,
            'tanggal' => '2026-10-03',
            'jumlah' => '8.000',
            'harga_beli' => '120.00',
            'total_harga' => '960.00',
        ]);
        $this->get(route('pembelian.history'))
            ->assertOk()
            ->assertSee(route('pembelian.edit', $pembelian));

        $this->put(route('pembelian.update', $pembelian), [
            'produk_id' => $produk->id,
            'satuan' => 'kg',
            'tanggal' => '2026-10-03',
            'jumlah' => '2',
            'harga_beli' => '120',
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame('5.000', $produk->fresh()->stok);
        $this->assertSame('8.000', $pembelian->fresh()->jumlah);
    }

    public function test_sale_transactions_can_be_edited_and_recalculate_stock_and_profit(): void
    {
        $pemilik = $this->createPemilik('Budi');
        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Gula',
            'tanggal' => '2026-10-01',
            'satuan' => 'kg',
            'jumlah' => '10',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $produk = Produk::query()->where('pemilik_id', $pemilik->id)->firstOrFail();
        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $pemilik->kode,
            'produk_id' => $produk->id,
            'tanggal' => '2026-10-02',
            'satuan' => 'kg',
            'jumlah' => '3',
            'harga_jual' => '30000',
        ])->assertRedirect(route('penjualan.create.list'));

        $penjualan = Penjualan::query()->firstOrFail();
        $this->get(route('penjualan.edit', $penjualan))
            ->assertOk()
            ->assertSee('name="produk_id"', false)
            ->assertSee('name="satuan"', false)
            ->assertSee('name="tanggal"', false)
            ->assertSee('name="jumlah"', false)
            ->assertSee('name="harga_jual"', false)
            ->assertSee('value="3" required', false)
            ->assertSee('value="30.000,00"', false);

        $this->put(route('penjualan.update', $penjualan), [
            'produk_id' => $produk->id,
            'satuan' => 'kg',
            'tanggal' => '2026-10-03',
            'jumlah' => '4',
            'harga_jual' => '175',
        ])->assertRedirect(route('penjualan.create.list'))
            ->assertSessionHas('success', 'Transaksi penjualan berhasil diperbarui dan stok disesuaikan.');

        $this->assertSame('6.000', $produk->fresh()->stok);
        $this->assertSame('175.00', $produk->fresh()->harga_jual);
        $this->assertDatabaseHas('penjuwalans', [
            'id' => $penjualan->id,
            'tanggal' => '2026-10-03',
            'jumlah' => '4.000',
            'harga_jual' => '175.00',
            'total_harga_beli' => '400.00',
            'total_harga_jual' => '700.00',
            'laba' => '300.00',
        ]);
        $this->get(route('penjualan.history'))
            ->assertOk()
            ->assertSee(route('penjualan.edit', $penjualan));

        $this->put(route('penjualan.update', $penjualan), [
            'produk_id' => $produk->id,
            'satuan' => 'kg',
            'tanggal' => '2026-10-03',
            'jumlah' => '11',
            'harga_jual' => '175',
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame('6.000', $produk->fresh()->stok);
        $this->assertSame('4.000', $penjualan->fresh()->jumlah);
    }

    public function test_editing_transactions_to_another_product_moves_stock_between_products(): void
    {
        $ownerA = $this->createPemilik('Pemilik A');
        $ownerB = $this->createPemilik('Pemilik B');

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $ownerA->kode,
            'nama_barang' => 'Barang A',
            'tanggal' => '2026-10-01',
            'satuan' => 'kg',
            'jumlah' => '5',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));
        $purchaseToMove = Pembelian::query()->firstOrFail();
        $productA = Produk::query()->where('pemilik_id', $ownerA->id)->firstOrFail();

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $ownerB->kode,
            'nama_barang' => 'Barang B',
            'tanggal' => '2026-10-01',
            'satuan' => 'box',
            'jumlah' => '5',
            'harga_beli' => '200',
        ])->assertRedirect(route('pembelian.create.list'));
        $productB = Produk::query()->where('pemilik_id', $ownerB->id)->firstOrFail();

        $this->put(route('pembelian.update', $purchaseToMove), [
            'produk_id' => $productB->id,
            'satuan' => 'box',
            'tanggal' => '2026-10-02',
            'jumlah' => '2',
            'harga_beli' => '250',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->assertSame('0.000', $productA->fresh()->stok);
        $this->assertSame('7.000', $productB->fresh()->stok);
        $this->assertSame((int) $productB->id, (int) $purchaseToMove->fresh()->produk_id);

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $ownerB->kode,
            'produk_id' => $productB->id,
            'tanggal' => '2026-10-03',
            'satuan' => 'box',
            'jumlah' => '2',
            'harga_jual' => '300',
        ])->assertRedirect(route('penjualan.create.list'));
        $saleToMove = Penjualan::query()->firstOrFail();

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $ownerA->kode,
            'nama_barang' => 'Barang A',
            'tanggal' => '2026-10-03',
            'satuan' => 'kg',
            'jumlah' => '4',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->put(route('penjualan.update', $saleToMove), [
            'produk_id' => $productA->id,
            'satuan' => 'kg',
            'tanggal' => '2026-10-04',
            'jumlah' => '3',
            'harga_jual' => '350',
        ])->assertRedirect(route('penjualan.create.list'));

        $this->assertSame('1.000', $productA->fresh()->stok);
        $this->assertSame('7.000', $productB->fresh()->stok);
        $this->assertDatabaseHas('penjuwalans', [
            'id' => $saleToMove->id,
            'produk_id' => $productA->id,
            'satuan' => 'kg',
            'jumlah' => '3.000',
            'harga_beli_satuan' => '100.00',
            'total_harga_beli' => '300.00',
            'total_harga_jual' => '1050.00',
            'laba' => '750.00',
        ]);
    }

    public function test_profit_is_green_and_loss_is_red_in_the_profit_and_loss_report(): void
    {
        $profitOwner = $this->createPemilik('Pemilik laba');
        $lossOwner = $this->createPemilik('Pemilik rugi');

        foreach ([[$profitOwner, '100'], [$lossOwner, '150']] as [$owner, $purchasePrice]) {
            $this->post(route('pembelian.store'), [
                'pemilik_kode' => $owner->kode,
                'nama_barang' => 'Barang '.$owner->nama,
                'tanggal' => '2026-10-16',
                'satuan' => 'pcs',
                'jumlah' => '1',
                'harga_beli' => $purchasePrice,
            ])->assertRedirect(route('pembelian.create.list'));

            $product = Produk::query()->where('pemilik_id', $owner->id)->firstOrFail();

            $this->post(route('penjualan.store'), [
                'pemilik_kode' => $owner->kode,
                'produk_id' => $product->id,
                'tanggal' => '2026-10-16',
                'satuan' => 'pcs',
                'jumlah' => '1',
                'harga_jual' => $owner->is($profitOwner) ? '160' : '100',
            ])->assertRedirect(route('penjualan.create.list'));
        }

        $this->get(route('laporan.laba-rugi', [
            'tanggal_mulai' => '2026-10-16',
            'tanggal_selesai' => '2026-10-16',
        ]))
            ->assertOk()
            ->assertSee('class="fw-semibold mb-0 text-success">Rp 10,00', false)
            ->assertSee('data-order="60.00" title="Laba">Rp 60,00</td>', false)
            ->assertSee('data-order="-50.00" title="Rugi">Rp -50,00</td>', false)
            ->assertSee('#laba-rugi-table td:last-child', false)
            ->assertSee('position: sticky;', false);

        $this->get(route('laporan.laba-rugi', [
            'tanggal_mulai' => '2026-10-16',
            'tanggal_selesai' => '2026-10-16',
            'pemilik' => $profitOwner->kode,
        ]))
            ->assertOk()
            ->assertSee('Pemilik laba')
            ->assertSee('Barang Pemilik laba')
            ->assertDontSee('Barang Pemilik rugi')
            ->assertSee('Laporan Laba Rugi - Pemilik laba')
            ->assertDontSee('<td>'.$profitOwner->kode.'</td>', false)
            ->assertSee('name="tanggal_mulai" value="2026-10-16"', false)
            ->assertSee('<option value="'.$profitOwner->kode.'" selected>', false);
    }

    public function test_sales_cannot_exceed_available_stock(): void
    {
        $pemilik = $this->createPemilik('Citra');
        $produk = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Susu',
            'satuan' => 'botol',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $pemilik->kode,
            'nama_barang' => 'Susu',
            'tanggal' => '2026-10-16',
            'satuan' => 'per botol',
            'jumlah' => '1',
            'harga_beli' => '10',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->from(route('penjualan.create'))
            ->post(route('penjualan.store'), [
                'pemilik_kode' => $pemilik->kode,
                'produk_id' => $produk->id,
                'tanggal' => '2026-10-16',
                'satuan' => 'per botol',
                'jumlah' => '1.001',
                'harga_jual' => '15',
            ])
            ->assertRedirect(route('penjualan.create'))
            ->assertSessionHasErrors('jumlah');

        $this->assertSame('1.000', $produk->fresh()->stok);
        $this->assertDatabaseCount('penjuwalans', 0);
    }

    public function test_sales_require_a_selected_existing_product(): void
    {
        $pemilik = $this->createPemilik('Dedi');
        $this->from(route('penjualan.create'))
            ->post(route('penjualan.store'), [
                'pemilik_kode' => $pemilik->kode,
                'produk_id' => 999999,
                'tanggal' => '2026-10-16',
                'satuan' => 'per pcs',
                'jumlah' => '1',
                'harga_jual' => '15',
            ])
            ->assertRedirect(route('penjualan.create'))
            ->assertSessionHasErrors('produk_id');

        $this->assertDatabaseCount('penjuwalans', 0);
    }

    public function test_sales_form_only_lists_products_with_available_stock_and_prefills_the_unit(): void
    {
        $pemilik = $this->createPemilik('Eka');
        $availableProduct = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Kopi tersedia',
            'satuan' => 'pcs',
        ]);
        $availableProduct->stok = '3';
        $availableProduct->save();

        $emptyProduct = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Kopi habis',
            'satuan' => 'pcs',
        ]);

        $this->get(route('penjualan.create'))
            ->assertOk()
            ->assertSee('id="produk_id" name="produk_id"', false)
            ->assertSee('id="pemilik_nama"', false)
            ->assertSee('readonly', false)
            ->assertSee('Kopi tersedia')
            ->assertDontSee('Kopi habis')
            ->assertSee('data-unit="pcs"', false)
            ->assertSee('data-owner-code="'.$pemilik->kode.'"', false)
            ->assertDontSee('value="'.$emptyProduct->id.'" data-owner-code=', false);
    }

    public function test_dashboard_shows_live_bookkeeping_data_and_chart_series(): void
    {
        $produk = Produk::query()->create([
            'nama' => 'Barang Dashboard',
            'satuan' => 'pcs',
        ]);
        $produk->stok = '4';
        $produk->harga_beli = '500';
        $produk->harga_jual = '700';
        $produk->save();

        Pembelian::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => now()->toDateString(),
            'satuan' => 'per pcs',
            'jumlah' => '2',
            'harga_beli' => '500',
            'total_harga' => '1000',
        ]);
        Penjualan::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => now()->toDateString(),
            'satuan' => 'per pcs',
            'jumlah' => '1',
            'harga_beli_satuan' => '500',
            'harga_jual' => '700',
            'total_harga_beli' => '500',
            'total_harga_jual' => '700',
            'laba' => '200',
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Penjualan & Pembelian Harian', false)
            ->assertSee('Ringkasan Penjualan')
            ->assertSee('border-danger', false)
            ->assertSee('bg-danger', false)
            ->assertSee('Laba / Rugi Bulan Ini')
            ->assertSee('Barang Dashboard')
            ->assertSee('(per pcs)')
            ->assertSee('Unit')
            ->assertSee('Rp 700')
            ->assertSee('Rp 200')
            ->assertDontSee('assets/images/profile/user-1.jpg', false)
            ->assertDontSee('app-layout-footer', false)
            ->assertDontSee('Ringkasan Stok Barang')
            ->assertDontSee('AdminMart.com')
            ->assertDontSee('ThemeWagon')
            ->assertSee('dashboardData')
            ->assertDontSee('John Doe')
            ->assertDontSee('Boat Headphone')
            ->assertDontSee('$36,358');

        $dashboardData = $response->viewData('dashboardData');
        $lastChartPoint = count($dashboardData['sales']) - 1;

        $this->assertSame(700.0, $dashboardData['sales'][$lastChartPoint]);
        $this->assertSame(1000.0, $dashboardData['purchases'][$lastChartPoint]);
        $this->assertSame(200.0, $dashboardData['profit'][$lastChartPoint]);
    }

    public function test_bookkeeping_pages_render_with_the_modernize_layout(): void
    {
        foreach ([
            'pemilik.index',
            'pembelian.index',
            'pembelian.create',
            'pembelian.master-data',
            'penjualan.index',
            'penjualan.create',
            'penjualan.master-data',
            'laporan.laba-rugi',
        ] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('assets/css/styles.min.css')
                ->assertSee('Dashboard')
                ->assertSee('MASTER DATA')
                ->assertSee('Master ID Nama')
                ->assertSee('Create Penjualan')
                ->assertSee('Create Pembelian')
                ->assertSee('<div class="bmt-brand text-nowrap">', false)
                ->assertDontSee('<a href="http://localhost" class="bmt-brand', false)
                ->assertSee('>Edit Account</span>', false)
                ->assertSee('ti-user', false)
                ->assertSee('>Edit Account</span>', false)
                ->assertSee('ti-user', false)
                ->assertDontSee('app-layout-footer', false)
                ->assertSee('>Logout</span>', false)
                ->assertDontSee('>Login</span>', false);
        }

        $this->get(route('pembelian.index'))
            ->assertSee('View Pembelian')
            ->assertSee('id="pembelian-table"', false)
            ->assertSee('id="pembelian-table-date-from"', false)
            ->assertSee('data-auto-submit-owner-filter', false)
            ->assertDontSee('Filter Nama')
            ->assertSee('id="pembelian-table-date-to"', false)
            ->assertDontSee('Reset tanggal')
            ->assertSee('cdn.datatables.net/2.3.4')
            ->assertSee("search: 'Cari:'", false)
            ->assertDontSee('Dari tanggal')
            ->assertDontSee('Sampai tanggal')
            ->assertDontSee('Cari nama barang')
            ->assertSee('Belum ada data pembelian.')
            ->assertSee('ti-basket')
            ->assertDontSee('ti-shopping-bag')
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Nama Barang')
            ->assertSee('Unit')
            ->assertSee('Harga')
            ->assertSee('Total')
            ->assertDontSee('+ Create Pembelian');

        $this->get(route('penjualan.index'))
            ->assertSee('View Penjualan')
            ->assertSee('id="penjualan-table"', false)
            ->assertSee('data-auto-submit-owner-filter', false)
            ->assertDontSee('Filter Nama')
            ->assertSee('id="penjualan-table-date-from"', false)
            ->assertSee('id="penjualan-table-date-to"', false)
            ->assertDontSee('Reset tanggal')
            ->assertSee('cdn.datatables.net/2.3.4')
            ->assertSee("search: 'Cari:'", false)
            ->assertDontSee('Dari tanggal')
            ->assertDontSee('Sampai tanggal')
            ->assertDontSee('Cari nama barang')
            ->assertSee('Belum ada data penjualan.')
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Nama Barang')
            ->assertSee('Unit')
            ->assertSee('Harga')
            ->assertSee('Total')
            ->assertDontSee('+ Create Penjualan');

        $this->get(route('laporan.laba-rugi'))
            ->assertOk()
            ->assertDontSee('Periode laporan: dari tanggal')
            ->assertDontSee('Periode laporan: sampai tanggal')
            ->assertSee('id="laba-rugi-table"', false)
            ->assertSee('id="laba-rugi-table-date-from"', false)
            ->assertSee('id="laba-rugi-table-date-to"', false)
            ->assertSee('name="tanggal_mulai"', false)
            ->assertSee('name="tanggal_selesai"', false)
            ->assertDontSee('Reset tanggal')
            ->assertSee('name="pemilik"', false)
            ->assertSee('data-auto-submit-owner-filter', false)
            ->assertDontSee('<th>ID Pemilik</th>', false)
            ->assertDontSee('<th>Nama Pemilik</th>', false)
            ->assertSee('buttons/3.2.5/js/dataTables.buttons.min.js')
            ->assertSee('buttons.html5.min.js')
            ->assertSee("extend: 'csvHtml5'", false)
            ->assertSee("extend: 'excelHtml5'", false)
            ->assertSee("extend: 'pdfHtml5'", false)
            ->assertSee('justify-content: center', false)
            ->assertSee("api.rows({ search: 'applied', order: 'applied' })", false)
            ->assertSee('Harga Beli')
            ->assertSee('Harga Jual');

        $this->get(route('pembelian.create'))
            ->assertSee('Create Pembelian')
            ->assertDontSee('Kelola Barang');
        $this->get(route('pembelian.create'))
            ->assertSee('id="pemilik_nama"', false)
            ->assertSee('readonly', false);

        $this->get(route('penjualan.create'))
            ->assertSee('Create Penjualan')
            ->assertDontSee('Kelola Barang')
            ->assertSee('id="produk_id"', false)
            ->assertSee('id="pemilik_nama"', false)
            ->assertSee('readonly', false);

        $produk = Produk::query()->create([
            'pemilik_id' => Pemilik::query()->where('kode', '001')->value('id'),
            'nama' => 'Contoh Form',
            'satuan' => 'pcs',
        ]);
        $produk->harga_beli = '8250';
        $produk->harga_jual = '12500';
        $produk->stok = '2';
        $produk->save();

        $this->get(route('pembelian.create'))
            ->assertSee('Tambah Pembelian')
            ->assertSee('ID Pemilik <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Nama Barang <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Tgl <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Unit <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Harga beli <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Jumlah <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Unit')
            ->assertSee('Jumlah')
            ->assertSee('Harga beli')
            ->assertSee('name="nama_barang"', false)
            ->assertDontSee('<select', false)
            ->assertDontSee('name="produk_id"', false)
            ->assertSee('name="pemilik_kode"', false)
            ->assertSee('id="nomor" value="1" readonly', false)
            ->assertDontSee('id="nama_barang" name="nama_barang" value="" maxlength="150" readonly', false)
            ->assertDontSee('id="satuan" name="satuan" value="" placeholder="per pcs / per botol" maxlength="30" readonly', false)
            ->assertSee('name="harga_beli"', false)
            ->assertSee('Total harga pembelian (Jumlah × Harga)')
            ->assertSee('type="text" id="satuan" name="satuan"', false)
            ->assertSee('for="jumlah">Jumlah <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('id="total_jumlah" value="0,00" readonly', false)
            ->assertSee('action="http://localhost/pembelian"', false);

        $this->get(route('penjualan.create'))
            ->assertSee('Tambah Penjualan')
            ->assertSee('ID Pemilik <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Nama Barang <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Tgl <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Unit <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Harga jual <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('Jumlah <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('No.')
            ->assertSee('Tgl')
            ->assertSee('Unit')
            ->assertSee('Harga jual')
            ->assertSee('name="produk_id"', false)
            ->assertSee('name="pemilik_kode"', false)
            ->assertSee('Contoh Form')
            ->assertSee('id="produk_id"', false)
            ->assertSee('id="nomor" value="1" readonly', false)
            ->assertDontSee('id="nama_barang" name="nama_barang"', false)
            ->assertSee('id="satuan" name="satuan"', false)
            ->assertSee('type="date" id="tanggal" name="tanggal"', false)
            ->assertSee('name="harga_jual"', false)
            ->assertSee('Total harga penjualan (Jumlah × Harga)')
            ->assertSee('id="satuan" name="satuan"', false)
            ->assertSee('type="text" id="satuan" name="satuan"', false)
            ->assertSee('id="total_jumlah" value="0,00" readonly', false)
            ->assertSee('for="harga_jual">Harga jual <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('for="jumlah">Jumlah <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('action="http://localhost/penjualan"', false);
    }

    public function test_purchase_and_sales_tables_include_all_rows_for_datatables(): void
    {
        $produk = Produk::query()->create([
            'pemilik_id' => Pemilik::query()->where('kode', '000')->value('id'),
            'nama' => 'Barang tabel',
            'satuan' => 'pcs',
        ]);

        foreach (range(1, 21) as $amount) {
            Pembelian::query()->create([
                'produk_id' => $produk->id,
                'tanggal' => '2026-10-16',
                'jumlah' => '1',
                'harga_beli' => (string) $amount,
                'total_harga' => (string) $amount,
            ]);

            Penjualan::query()->create([
                'produk_id' => $produk->id,
                'tanggal' => '2026-10-16',
                'jumlah' => '1',
                'harga_beli_satuan' => '1',
                'harga_jual' => (string) ($amount + 100),
                'total_harga_beli' => '1',
                'total_harga_jual' => (string) ($amount + 100),
                'laba' => (string) ($amount + 99),
            ]);
        }

        $this->get(route('pembelian.index'))
            ->assertOk()
            ->assertSee('Rp 21,00')
            ->assertSee('data-date="2026-10-16"', false)
            ->assertDontSee('pagination');

        $this->get(route('penjualan.index'))
            ->assertOk()
            ->assertSee('Rp 121,00')
            ->assertSee('data-date="2026-10-16"', false)
            ->assertDontSee('pagination');

        $pemilik = $this->createPemilik('Filter test');
        $otherOwnerProduct = Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => 'Barang orang lain',
            'satuan' => 'pcs',
        ]);
        Pembelian::query()->create([
            'produk_id' => $otherOwnerProduct->id,
            'tanggal' => '2026-10-16',
            'jumlah' => '1',
            'harga_beli' => '500',
            'total_harga' => '500',
        ]);
        Penjualan::query()->create([
            'produk_id' => $otherOwnerProduct->id,
            'tanggal' => '2026-10-16',
            'jumlah' => '1',
            'harga_beli_satuan' => '500',
            'harga_jual' => '600',
            'total_harga_beli' => '500',
            'total_harga_jual' => '600',
            'laba' => '100',
        ]);

        $this->get(route('pembelian.index', [
            'pemilik' => $pemilik->kode,
            'tanggal_mulai' => '2026-10-16',
            'tanggal_selesai' => '2026-10-16',
        ]))
            ->assertOk()
            ->assertSee('Barang orang lain')
            ->assertDontSee('Barang tabel')
            ->assertSee('Filter test')
            ->assertSee('id="pembelian-table-date-from-query" name="tanggal_mulai" value="2026-10-16"', false);
        $this->get(route('penjualan.index', [
            'pemilik' => $pemilik->kode,
            'tanggal_mulai' => '2026-10-16',
            'tanggal_selesai' => '2026-10-16',
        ]))
            ->assertOk()
            ->assertSee('Barang orang lain')
            ->assertDontSee('Barang tabel')
            ->assertSee('Filter test')
            ->assertSee('id="penjualan-table-date-from-query" name="tanggal_mulai" value="2026-10-16"', false);

        $this->get(route('laporan.laba-rugi', [
            'tanggal_mulai' => '2026-10-16',
            'tanggal_selesai' => '2026-10-16',
        ]))
            ->assertOk()
            ->assertSee('id="laba-rugi-table"', false)
            ->assertSee('Rp 121,00')
            ->assertSee('data-date="2026-10-16"', false)
            ->assertDontSee('pagination');

        $this->get(route('pembelian.create'))
            ->assertOk()
            ->assertSee('id="nomor" value="23" readonly', false);

        $this->get(route('penjualan.create'))
            ->assertOk()
            ->assertSee('id="nomor" value="23" readonly', false);
    }

    public function test_purchases_keep_same_product_names_separate_for_different_owners(): void
    {
        $ownerOne = $this->createPemilik('Pak Bambang');
        $ownerTwo = $this->createPemilik('Bu Sari');

        foreach ([
            [$ownerOne, '5'],
            [$ownerTwo, '8'],
        ] as [$owner, $quantity]) {
            $this->post(route('pembelian.store'), [
                'pemilik_kode' => $owner->kode,
                'nama_barang' => 'Beras',
                'tanggal' => '2026-10-16',
                'satuan' => 'kg',
                'jumlah' => $quantity,
                'harga_beli' => '10000',
            ])->assertRedirect(route('pembelian.create.list'));
        }

        $products = Produk::query()->where('nama', 'Beras')->orderBy('pemilik_id')->get();

        $this->assertCount(2, $products);
        $this->assertSame(['5.000', '8.000'], $products->pluck('stok')->all());
        $this->assertSame([$ownerOne->id, $ownerTwo->id], $products->pluck('pemilik_id')->all());

        $this->get(route('pembelian.index', ['pemilik' => $ownerOne->kode]))
            ->assertSee('Pak Bambang')
            ->assertDontSee('<td>Bu Sari</td>', false);
    }

    public function test_logout_clears_the_session_and_redirects_to_login(): void
    {
        $this->withSession(['temporary_value' => 'present'])
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('temporary_value');
    }

    public function test_login_authenticates_and_protects_bookkeeping_pages(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('pembelian.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $this->testUser->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('pembelian.index'));

        $this->assertAuthenticatedAs($this->testUser);
        $this->get(route('pembelian.index'))->assertOk();
    }

    public function test_public_account_registration_is_disabled(): void
    {
        $this->post(route('logout'));
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Set up the first account')
            ->assertDontSee('Create Account');
    }

    public function test_account_details_and_password_can_be_updated(): void
    {
        $this->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Logo Koperasi Konsumen Cinde Barokah Sejahtera')
            ->assertSee('Edit Account');

        $this->put(route('account.update'), [
            'name' => 'Updated Admin',
            'email' => 'updated@example.test',
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('account.edit'))
            ->assertSessionHas('success', 'Account details updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->testUser->id,
            'name' => 'Updated Admin',
            'email' => 'updated@example.test',
        ]);

        Auth::logout();
        $this->post(route('login.store'), [
            'email' => 'updated@example.test',
            'password' => 'new-secure-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->testUser);
    }

    public function test_names_receive_sequential_display_ids_and_are_available_for_transaction_entry(): void
    {
        $this->assertDatabaseHas('pemiliks', [
            'kode' => '001',
            'nama' => 'Bambang',
        ]);

        $this->post(route('pemilik.store'), ['nama' => 'Pak Bambang'])
            ->assertRedirect(route('pemilik.index'))
            ->assertSessionHas('success', 'Nama berhasil disimpan dengan ID 002.');

        $this->post(route('pemilik.store'), ['nama' => 'Bu Sari'])
            ->assertSessionHas('success', 'Nama berhasil disimpan dengan ID 003.');

        $this->get(route('pemilik.index'))
            ->assertOk()
            ->assertSee('001')
            ->assertSee('Bambang')
            ->assertSee('002')
            ->assertSee('Pak Bambang')
            ->assertSee('003')
            ->assertSee('Bu Sari');
    }

    public function test_product_master_supports_editing_and_permanent_deletion(): void
    {
        $owner = $this->createPemilik('Pemilik produk');

        $this->post(route('produk.store'), [
            'pemilik_kode' => $owner->kode,
            'nama' => 'Nama lama',
            'satuan' => 'pcs',
            'harga_jual' => '150',
        ])->assertRedirect(route('penjualan.create.list'));

        $product = Produk::query()->where('nama', 'Nama lama')->firstOrFail();
        $this->get(route('produk.index'))
            ->assertRedirect(route('penjualan.create.list'));
        $this->get(route('penjualan.create.list'))
            ->assertOk()
            ->assertDontSee(route('produk.edit', $product))
            ->assertDontSee(route('produk.destroy', $product));

        $this->get(route('produk.create'))
            ->assertOk()
            ->assertSee('Simpan Barang');

        $this->put(route('produk.update', $product), [
            'pemilik_kode' => $owner->kode,
            'nama' => 'Nama baru',
            'satuan' => 'kg',
            'harga_jual' => '250',
        ])->assertRedirect(route('penjualan.create.list'))
            ->assertSessionHas('success', 'Data barang berhasil diperbarui.');

        $this->assertDatabaseHas('produks', [
            'id' => $product->id,
            'nama' => 'Nama baru',
            'satuan' => 'kg',
            'harga_jual' => '250.00',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $owner->kode,
            'nama_barang' => 'Nama baru',
            'tanggal' => '2026-10-16',
            'satuan' => 'kg',
            'jumlah' => '2',
            'harga_beli' => '100',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->delete(route('produk.destroy', $product))
            ->assertRedirect(route('penjualan.create.list'))
            ->assertSessionHas('success', 'Barang dan seluruh transaksi terkait berhasil dihapus.');

        $this->assertDatabaseMissing('produks', ['id' => $product->id]);
        $this->assertDatabaseMissing('pembelians', ['produk_id' => $product->id]);
    }

    public function test_transaction_history_opens_as_a_separate_page_with_edit_actions(): void
    {
        $owner = $this->createPemilik('Pemilik stok otomatis');
        $product = Produk::query()->create([
            'pemilik_id' => $owner->id,
            'nama' => 'NARUTO',
            'satuan' => 'Botol',
            'harga_beli' => '20000',
            'harga_jual' => '25000',
            'stok' => '0',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $owner->kode,
            'nama_barang' => 'NARUTO',
            'tanggal' => '2026-10-02',
            'satuan' => 'Botol',
            'jumlah' => '10',
            'harga_beli' => '20000',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $owner->kode,
            'produk_id' => $product->id,
            'tanggal' => '2026-10-06',
            'satuan' => 'Botol',
            'jumlah' => '4',
            'harga_jual' => '25000',
        ])->assertRedirect(route('penjualan.create.list'));

        $this->assertSame('6.000', $product->fresh()->stok);

        $this->get(route('penjualan.history'))
            ->assertOk()
            ->assertSee('Riwayat Penjualan')
            ->assertSee('NARUTO')
            ->assertSee('>4</td>', false)
            ->assertSee(route('penjualan.edit', Penjualan::query()->firstOrFail()))
            ->assertSee(route('penjualan.create.list'));

        $product->forceFill(['stok' => '-2'])->save();

        $this->get(route('penjualan.create.list'))
            ->assertOk()
            ->assertSee('action="'.route('penjualan.store').'"', false)
            ->assertDontSee('Riwayat Penjualan</h5>');
    }

    public function test_transaction_history_shows_edit_and_delete_icon_actions(): void
    {
        $owner = $this->createPemilik('Pemilik aksi');
        Produk::query()->create([
            'pemilik_id' => $owner->id,
            'nama' => 'Barang aksi',
            'satuan' => 'pcs',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $owner->kode,
            'nama_barang' => 'Barang aksi',
            'tanggal' => '2026-10-02',
            'satuan' => 'pcs',
            'jumlah' => '5',
            'harga_beli' => '1000',
        ])->assertRedirect(route('pembelian.create.list'));

        $pembelian = Pembelian::query()->firstOrFail();

        $this->get(route('pembelian.history'))
            ->assertOk()
            ->assertSee('action="'.route('pembelian.destroy', $pembelian).'"', false)
            ->assertSee('href="'.route('pembelian.edit', $pembelian).'"', false)
            ->assertSee('ti ti-edit', false)
            ->assertSee('ti ti-trash', false);
    }

    public function test_deleting_transactions_removes_the_row_and_adjusts_stock(): void
    {
        $owner = $this->createPemilik('Pemilik hapus');
        $product = Produk::query()->create([
            'pemilik_id' => $owner->id,
            'nama' => 'Barang hapus',
            'satuan' => 'pcs',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $owner->kode,
            'nama_barang' => 'Barang hapus',
            'tanggal' => '2026-10-02',
            'satuan' => 'pcs',
            'jumlah' => '5',
            'harga_beli' => '1000',
        ]);

        $pembelian = Pembelian::query()->firstOrFail();

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $owner->kode,
            'produk_id' => $product->id,
            'tanggal' => '2026-10-03',
            'satuan' => 'pcs',
            'jumlah' => '2',
            'harga_jual' => '1500',
        ]);

        $penjualan = Penjualan::query()->firstOrFail();
        $this->assertSame('3.000', $product->fresh()->stok);

        $this->delete(route('penjualan.destroy', $penjualan))
            ->assertRedirect(route('penjualan.history'));
        $this->assertSame('5.000', $product->fresh()->stok);
        $this->assertDatabaseMissing('penjuwalans', ['id' => $penjualan->id]);

        $this->delete(route('pembelian.destroy', $pembelian))
            ->assertRedirect(route('pembelian.history'));
        $this->assertSame('0.000', $product->fresh()->stok);
        $this->assertDatabaseMissing('pembelians', ['id' => $pembelian->id]);
    }

    public function test_purchase_cannot_be_deleted_when_its_stock_was_already_sold(): void
    {
        $owner = $this->createPemilik('Pemilik stok terpakai');
        $product = Produk::query()->create([
            'pemilik_id' => $owner->id,
            'nama' => 'Barang terpakai',
            'satuan' => 'pcs',
        ]);

        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $owner->kode,
            'nama_barang' => 'Barang terpakai',
            'tanggal' => '2026-10-02',
            'satuan' => 'pcs',
            'jumlah' => '5',
            'harga_beli' => '1000',
        ]);

        $pembelian = Pembelian::query()->firstOrFail();

        $this->post(route('penjualan.store'), [
            'pemilik_kode' => $owner->kode,
            'produk_id' => $product->id,
            'tanggal' => '2026-10-03',
            'satuan' => 'pcs',
            'jumlah' => '2',
            'harga_jual' => '1500',
        ]);

        $this->delete(route('pembelian.destroy', $pembelian))
            ->assertSessionHasErrors('pembelian');

        $this->assertSame('3.000', $product->fresh()->stok);
        $this->assertDatabaseHas('pembelians', ['id' => $pembelian->id]);
    }

    public function test_owner_master_supports_editing_and_deleting_linked_records(): void
    {
        $owner = Pemilik::query()->where('kode', '001')->firstOrFail();
        $product = Produk::query()->create([
            'pemilik_id' => $owner->id,
            'nama' => 'Barang milik Bambang',
            'satuan' => 'pcs',
        ]);
        $product->stok = '1';
        $product->save();

        Pembelian::query()->create([
            'produk_id' => $product->id,
            'tanggal' => '2026-10-16',
            'jumlah' => '1',
            'harga_beli' => '100',
            'total_harga' => '100',
        ]);
        Penjualan::query()->create([
            'produk_id' => $product->id,
            'tanggal' => '2026-10-16',
            'jumlah' => '1',
            'harga_beli_satuan' => '100',
            'harga_jual' => '150',
            'total_harga_beli' => '100',
            'total_harga_jual' => '150',
            'laba' => '50',
        ]);

        $this->put(route('pemilik.update', $owner), ['nama' => 'Bambang Baru'])
            ->assertRedirect(route('pemilik.index'))
            ->assertSessionHas('success', 'Nama pemilik berhasil diperbarui.');

        $this->get(route('pemilik.index'))
            ->assertOk()
            ->assertSee('Nama pemilik <span class="text-danger" aria-hidden="true">*</span>', false)
            ->assertSee('id="pemilik-table"', false)
            ->assertSee('cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js')
            ->assertSee("new DataTable('#pemilik-table'", false)
            ->assertSee(route('pemilik.edit', $owner))
            ->assertSee('ti-edit', false)
            ->assertSee('ti-trash', false)
            ->assertSee(route('pemilik.destroy', $owner))
            ->assertSee('seluruh transaksi terkait akan dihapus permanen.');

        $this->assertDatabaseHas('pemiliks', ['id' => $owner->id, 'nama' => 'Bambang Baru']);

        $this->delete(route('pemilik.destroy', $owner))
            ->assertRedirect(route('pemilik.index'))
            ->assertSessionHas('success', 'Pemilik, barang, dan seluruh transaksi terkait berhasil dihapus.');

        $this->assertDatabaseMissing('pemiliks', ['id' => $owner->id]);
        $this->assertDatabaseMissing('produks', ['id' => $product->id]);
        $this->assertDatabaseMissing('pembelians', ['produk_id' => $product->id]);
        $this->assertDatabaseMissing('penjuwalans', ['produk_id' => $product->id]);

        $this->post(route('pemilik.store'), ['nama' => 'Pemilik pengganti'])
            ->assertSessionHas('success', 'Nama berhasil disimpan dengan ID 001.');
    }

    public function test_last_owner_id_stays_prefilled_after_saving_a_transaction(): void
    {
        $pemilik = $this->createPemilik('Budi');
        $kode = $pemilik->kode;

        // Habls mencatat pembelian, form create tampil lagi — ID harus sudah terisi.
        $this->post(route('pembelian.store'), [
            'pemilik_kode' => $kode,
            'nama_barang' => 'Gula',
            'tanggal' => '2026-10-20',
            'satuan' => 'per kg',
            'jumlah' => '5',
            'harga_beli' => '15000',
        ])->assertRedirect(route('pembelian.create.list'));

        $this->get(route('pembelian.create'))
            ->assertSee('name="pemilik_kode" value="'.$kode.'"', false);
        $this->get(route('penjualan.create'))
            ->assertSee('name="pemilik_kode" value="'.$kode.'"', false);

        // ID terakhir juga menempel di form Tambah Barang (selected di dropdown).
        $this->post(route('produk.store'), [
            'pemilik_kode' => $kode,
            'nama' => 'Teh Pucuk',
            'satuan' => 'botol',
        ])->assertRedirect(route('penjualan.create.list'));

        $this->get(route('produk.create'))
            ->assertSee('value="'.$kode.'" selected', false);

        // ID pemilik yang sudah dihapus tidak boleh ikut terisi.
        $this->delete(route('pemilik.destroy', $pemilik))->assertRedirect(route('pemilik.index'));
        $this->get(route('pembelian.create'))
            ->assertDontSee('name="pemilik_kode" value="'.$kode.'"', false);
    }

    private function createPemilik(string $nama): Pemilik
    {
        $lastCode = (int) Pemilik::query()
            ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
            ->value('kode_terakhir');

        return Pemilik::query()->create([
            'kode' => str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT),
            'nama' => $nama,
        ]);
    }
}

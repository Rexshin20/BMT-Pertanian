<?php

namespace Tests\Feature;

use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PenjualanImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_penjualan_page_has_no_import_box(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('penjualan.create'))
            ->assertOk()
            ->assertDontSee('Impor dari Excel / CSV / PDF')
            ->assertDontSee('name="jenis" value="penjualan"', false);
    }

    public function test_old_penjualan_file_previews_and_saves_sales(): void
    {
        $pemilik = Pemilik::query()->updateOrCreate(['kode' => '777'], ['nama' => 'Pemilik Uji']);
        $produk = Produk::query()->create([
            'nama' => 'UJI PENJUALAN',
            'pemilik_id' => $pemilik->id,
            'satuan' => 'Pcs',
        ]);
        // stok & harga_beli bukan fillable, isi lewat atribut
        $produk->stok = '5.000';
        $produk->harga_beli = '80000';
        $produk->save();
        $this->actingAs(User::factory()->create());

        $file = UploadedFile::fake()->createWithContent('penjualan-lama.xlsx', $this->makeOldXlsx([
            ['NAMA', '', ':', 'Pemilik Uji'],
            [],
            ['HARI, TANGGAL : Sabtu, 03 Oktober 2026'],
            [],
            ['NO', 'NAMA BARANG', 'ISI BOTOL', 'BARANG TERJUAL', 'HARGA JUAL'],
            [1, 'UJI PENJUALAN', '1 Pcs', 3, 100000],
        ]));

        $response = $this->post(route('impor.preview'), ['jenis' => 'penjualan', 'berkas' => $file]);
        $response->assertOk();
        $response->assertViewHas('validCount', 1);
        $response->assertSee('UJI PENJUALAN');
        $response->assertSee('2026-10-03');

        $this->post(route('impor.simpan'), ['token' => session('impor_import.token')])
            ->assertRedirect(route('impor.index', ['jenis' => 'penjualan']));

        $penjualan = Penjualan::query()->where('produk_id', $produk->id)->firstOrFail();
        $this->assertSame('3.000', $penjualan->jumlah);
        $this->assertSame('100000.00', $penjualan->harga_jual);
        $this->assertSame('2026-10-03', $penjualan->tanggal->format('Y-m-d'));
        $this->assertSame('2.000', $produk->fresh()->stok);
    }

    private function makeOldXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }
}

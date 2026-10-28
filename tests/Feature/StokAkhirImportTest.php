<?php

namespace Tests\Feature;

use App\Models\Pemilik;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StokAkhirImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_sheet_with_empty_stock_column_falls_back_to_angka_column(): void
    {
        $pemilik = Pemilik::query()->updateOrCreate(['kode' => '777'], ['nama' => 'Pemilik Uji']);
        $this->actingAs(User::factory()->create());

        $file = UploadedFile::fake()->createWithContent('stok.xlsx', $this->makeOldXlsx([
            ['NAMA', '', ':', 'Pemilik Uji'],
            [],
            ['NO', 'NAMA BARANG', 'BERAT', 'ISI/BOTOL', 'BARANG TERJUAL', 'STOK AKHIR', 'KETERANGAN'],
            [1, 'UJI PIONEER', '', '3 Pcs', 2, '', ''],
            [2, 'UJI GEMFUR', '', '6 Botol', 3, '', ''],
        ]));

        $response = $this->post(route('impor.preview'), ['jenis' => 'stok-akhir', 'berkas' => $file]);
        $response->assertOk();
        $response->assertViewHas('validCount', 2);

        $token = session('impor_import.token');
        $this->post(route('impor.simpan'), ['token' => $token])
            ->assertRedirect(route('impor.index', ['jenis' => 'stok-akhir']));

        $pioneer = Produk::query()->where('nama', 'UJI PIONEER')->firstOrFail();
        $this->assertSame($pemilik->id, $pioneer->pemilik_id);
        $this->assertSame('3.000', $pioneer->stok);
        $this->assertSame('Pcs', $pioneer->satuan);
    }

    public function test_stock_column_is_used_when_filled(): void
    {
        Pemilik::query()->updateOrCreate(['kode' => '777'], ['nama' => 'Pemilik Uji']);
        $this->actingAs(User::factory()->create());

        $file = UploadedFile::fake()->createWithContent('stok.xlsx', $this->makeOldXlsx([
            ['NAMA', '', ':', 'Pemilik Uji'],
            [],
            ['NO', 'NAMA BARANG', 'ISI/BOTOL', 'BARANG TERJUAL', 'STOK AKHIR'],
            [1, 'UJI ORIXONE', '9 Botol', 2, 5],
        ]));

        $response = $this->post(route('impor.preview'), ['jenis' => 'stok-akhir', 'berkas' => $file]);
        $response->assertOk();
        $response->assertSee('UJI ORIXONE');

        $token = session('impor_import.token');
        $this->post(route('impor.simpan'), ['token' => $token]);

        $produk = Produk::query()->where('nama', 'UJI ORIXONE')->firstOrFail();
        $this->assertSame('5.000', $produk->stok);
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

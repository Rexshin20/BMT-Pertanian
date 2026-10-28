<?php

namespace Tests\Feature;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use App\Support\PembelianImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PembelianImportTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create();
        $this->actingAs($this->testUser);
    }

    public function test_xlsx_import_previews_and_saves_purchases(): void
    {
        $pemilik = $this->createPemilik('Ani', '001');

        $rows = [
            ['ID Pemilik', 'Nama Barang', 'Tanggal', 'Satuan', 'Jumlah', 'Harga Beli'],
            ['001', 'Pupuk Urea', '2026-10-22', 'kg', 10, 12500],
            ['001', 'Benih Jagung', '2026-10-23', 'sak', 5, 45000],
        ];

        $file = UploadedFile::fake()->createWithContent('pembelian.xlsx', $this->makeXlsx($rows));

        $response = $this->post(route('pembelian.import.preview'), ['berkas' => $file]);
        $response->assertOk();
        $response->assertSee('Pupuk Urea');
        $response->assertSee('Benih Jagung');

        $token = session('pembelian_import.token');
        $this->assertNotNull($token);

        $this->post(route('pembelian.import.store'), ['token' => $token])
            ->assertRedirect(route('pembelian.index'));

        $this->assertSame(2, Pembelian::query()->count());
        $this->assertSame(2, Produk::query()->count());

        $urea = Produk::query()->where('nama', 'Pupuk Urea')->firstOrFail();
        $this->assertSame('10.000', $urea->stok);
        $this->assertSame($pemilik->id, $urea->pemilik_id);
        $this->assertDatabaseHas('pembelians', [
            'produk_id' => $urea->id,
            'jumlah' => '10.000',
            'total_harga' => '125000.00',
        ]);
    }

    public function test_import_accepts_rows_without_satuan(): void
    {
        $this->createPemilik('Ani', '001');

        $rows = [
            ['ID Pemilik', 'Nama Barang', 'Tanggal', 'Satuan', 'Jumlah', 'Harga Beli'],
            ['001', 'Pupuk Urea', '2026-10-22', '', 10, 12500],
        ];

        $file = UploadedFile::fake()->createWithContent('pembelian.xlsx', $this->makeXlsx($rows));

        $response = $this->post(route('pembelian.import.preview'), ['berkas' => $file]);
        $response->assertOk();
        $response->assertSee('Pupuk Urea');

        $token = session('pembelian_import.token');
        $this->assertNotNull($token);

        $this->post(route('pembelian.import.store'), ['token' => $token])
            ->assertRedirect(route('pembelian.index'));

        $this->assertSame(1, Pembelian::query()->count());

        $urea = Produk::query()->where('nama', 'Pupuk Urea')->firstOrFail();
        $this->assertSame('pcs', $urea->satuan);
        $this->assertSame('10.000', $urea->stok);
    }

    public function test_pdf_import_previews_and_saves_purchases(): void
    {
        $this->createPemilik('Ani', '001');

        $file = UploadedFile::fake()->createWithContent('pembelian.pdf', $this->makePdf([
            'LAPORAN PEMBELIAN',
            'ID Pemilik  Nama Barang  Tanggal  Satuan  Jumlah  Harga Beli',
            '001  Pupuk Urea  2026-10-22  kg  10  12500',
            '001  Benih Jagung  2026-10-23  sak  5  45000',
        ]));

        $response = $this->post(route('pembelian.import.preview'), ['berkas' => $file]);
        if ($response->status() !== 200) {
            $this->fail('Redirect. Errors: '.json_encode(session('errors')?->all()));
        }
        $response->assertOk();
        $response->assertSee('Pupuk Urea');
        $response->assertSee('Benih Jagung');

        $token = session('pembelian_import.token');
        $this->assertNotNull($token);

        $this->post(route('pembelian.import.store'), ['token' => $token])
            ->assertRedirect(route('pembelian.index'));

        $this->assertSame(2, Pembelian::query()->count());

        $urea = Produk::query()->where('nama', 'Pupuk Urea')->firstOrFail();
        $this->assertSame('10.000', $urea->stok);
        $this->assertDatabaseHas('pembelians', [
            'produk_id' => $urea->id,
            'total_harga' => '125000.00',
        ]);
    }

    public function test_pdf_import_accepts_rows_without_satuan(): void
    {
        $this->createPemilik('Ani', '001');

        $file = UploadedFile::fake()->createWithContent('pembelian.pdf', $this->makePdf([
            'LAPORAN PEMBELIAN',
            'ID Pemilik  Nama Barang  Tanggal  Satuan  Jumlah  Harga Beli',
            '001  Pupuk Urea  2026-10-22  10  12500',
        ]));

        $response = $this->post(route('pembelian.import.preview'), ['berkas' => $file]);
        $response->assertOk();
        $response->assertSee('Pupuk Urea');

        $token = session('pembelian_import.token');
        $this->assertNotNull($token);

        $this->post(route('pembelian.import.store'), ['token' => $token])
            ->assertRedirect(route('pembelian.index'));

        $this->assertSame(1, Pembelian::query()->count());
    }

    public function test_import_rejects_rows_with_unknown_owner_or_invalid_numbers(): void
    {
        $this->createPemilik('Ani', '001');

        $rows = [
            ['ID Pemilik', 'Nama Barang', 'Tanggal', 'Satuan', 'Jumlah', 'Harga Beli'],
            ['999', 'Barang Tidak Ada', '2026-10-22', 'kg', 3, 1000],
            ['001', 'Barang Cacat', '2026-10-22', 'kg', 'abc', 1000],
        ];

        $file = UploadedFile::fake()->createWithContent('pembelian.xlsx', $this->makeXlsx($rows));

        $this->post(route('pembelian.import.preview'), ['berkas' => $file])
            ->assertOk()
            ->assertSee('Barang Tidak Ada')
            ->assertSee('Barang Cacat');

        $this->assertSame(0, Pembelian::query()->count());
    }

    public function test_import_store_requires_a_valid_session_token(): void
    {
        $this->post(route('pembelian.import.store'), ['token' => 'token-palsu'])
            ->assertRedirect(route('pembelian.create'))
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Pembelian::query()->count());
    }

    public function test_penjualan_import_recognizes_old_banyaknya_penjualan_header(): void
    {
        $this->createPemilik('Ani', '901');

        $rows = [
            ['ID Pemilik', 'NAMA BARANG', 'TANGGAL', 'BANYAKNYA PENJUALAN', 'HARGA JUAL'],
            ['901', 'Pupuk Urea', '2026-10-22', 3, 15000],
        ];

        $file = UploadedFile::fake()->createWithContent('lama.xlsx', $this->makeXlsx($rows));

        $response = $this->post(route('impor.preview'), [
            'jenis' => 'penjualan',
            'berkas' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('Pupuk Urea');
        $response->assertViewHas('validCount', 1);

        $this->post(route('impor.simpan'), ['token' => session('impor_import.token')]);

        $this->assertSame(1, Penjualan::query()->count());
    }

    public function test_header_with_three_known_columns_is_recognized(): void
    {
        $rows = [
            ['Nama Barang', 'Tanggal', 'Satuan'],
            ['Pupuk Urea', '2026-10-22', 'kg'],
        ];

        $file = UploadedFile::fake()->createWithContent('tanpa-jumlah.xlsx', $this->makeXlsx($rows));

        $response = $this->post(route('impor.preview'), [
            'jenis' => 'penjualan',
            'berkas' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('Pupuk Urea');
        $response->assertDontSee('Baris judul kolom tidak ditemukan');
    }

    public function test_nama_produk_is_preferred_over_kode_barang(): void
    {
        $this->createPemilik('Ani', '901');

        $rows = [
            ['ID Pemilik', 'KODE BARANG', 'NAMA PRODUK', 'TANGGAL', 'JUMLAH', 'HARGA BELI'],
            ['901', 'K-001', 'Pupuk Urea', '2026-10-22', 2, 5000],
        ];

        $file = UploadedFile::fake()->createWithContent('kode-barang.xlsx', $this->makeXlsx($rows));

        $response = $this->post(route('impor.preview'), [
            'jenis' => 'pembelian',
            'berkas' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('Pupuk Urea');
        $response->assertViewHas('validCount', 1);

        $this->post(route('impor.simpan'), ['token' => session('impor_import.token')]);

        $this->assertDatabaseHas('produks', ['nama' => 'Pupuk Urea']);
    }

    public function test_header_error_shows_detected_column_titles(): void
    {
        $rows = [
            ['Laporan Penjualan Lama', '', '', '', '', ''],
            ['Keterangan', 'Lainnya', '', '', '', ''],
        ];

        $file = UploadedFile::fake()->createWithContent('tanpa-header.xlsx', $this->makeXlsx($rows));

        $this->post(route('impor.preview'), [
            'jenis' => 'penjualan',
            'berkas' => $file,
        ])->assertRedirect(route('impor.index', ['jenis' => 'penjualan']));

        $pesan = session('errors')?->first('berkas') ?? '';
        $this->assertStringContainsString('Baris judul kolom tidak ditemukan', $pesan);
        $this->assertStringContainsString('Laporan Penjualan Lama', $pesan);
    }

    private function createPemilik(string $nama, ?string $kode = null): Pemilik
    {
        if ($kode === null) {
            $lastCode = (int) Pemilik::query()
                ->selectRaw('MAX(CAST(kode AS DECIMAL(20, 0))) AS kode_terakhir')
                ->value('kode_terakhir');
            $kode = str_pad((string) ($lastCode + 1), 3, '0', STR_PAD_LEFT);
        }

        return Pemilik::query()->firstOrCreate(
            ['kode' => $kode],
            ['nama' => $nama],
        );
    }

    private function makeXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        PembelianImport::writeTemplate($path);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }

    private function makePdf(array $lines): string
    {
        $content = "BT\n";
        $y = 720;
        foreach ($lines as $line) {
            $content .= "1 0 0 1 40 {$y} Tm ({$line}) Tj\n";
            $y -= 20;
        }
        $content .= "ET\n";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
        $objects[] = '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[$i] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }
}


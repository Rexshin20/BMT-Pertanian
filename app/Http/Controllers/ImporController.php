<?php

namespace App\Http\Controllers;

use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Pembelian;
use App\Models\Produk;
use App\Support\FinancialAmount;
use App\Support\PembelianImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImporController extends Controller
{
    private const LABEL = [
        'pembelian' => 'Pembelian',
        'penjualan' => 'Penjualan',
        'stok-akhir' => 'Stok Akhir',
    ];

    public function index(Request $request): View
    {
        $jenis = in_array($request->jenis, ['pembelian', 'penjualan', 'stok-akhir']) ? $request->jenis : 'pembelian';

        return view('impor.index', [
            'jenis' => $jenis,
            'label' => self::LABEL[$jenis] ?? 'Pembelian',
            'pemiliks' => Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get(),
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'mimes:xlsx,xls,csv,txt,pdf', 'max:5120'],
            'jenis' => ['required', 'string', 'in:pembelian,penjualan,stok-akhir'],
        ], [], ['berkas' => 'File', 'jenis' => 'Jenis data']);

        $berkas = $request->file('berkas');
        $jenis = $request->jenis;
        $pemiliks = Pemilik::query()->get();

        try {
            if ($jenis === 'pembelian' || $jenis === 'penjualan') {
                $rows = PembelianImport::parse($berkas->getRealPath(), $pemiliks, $berkas->getClientOriginalName(), $jenis);
            } else {
                $rows = self::parseStokAkhir($berkas->getRealPath(), $berkas->getClientOriginalName());
            }
        } catch (RuntimeException $e) {
            return redirect()->route('impor.index', ['jenis' => $jenis])
                ->withErrors(['berkas' => $e->getMessage()]);
        }

        $token = (string) Str::uuid();
        session(['impor_import' => ['token' => $token, 'jenis' => $jenis, 'rows' => $rows]]);

        return view('impor.preview', [
            'rows' => $rows,
            'token' => $token,
            'jenis' => $jenis,
            'label' => self::LABEL[$jenis] ?? 'Pembelian',
            'validCount' => collect($rows)->filter(fn ($row) => $row['errors'] === [])->count(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $pending = session('impor_import');

        if (! $pending || ! hash_equals($pending['token'], (string) $request->input('token'))) {
            return redirect()->route('impor.index', ['jenis' => $pending['jenis'] ?? 'pembelian'])
                ->withErrors(['berkas' => 'Sesi impor kedaluwarsa. Silakan upload ulang file.']);
        }

        $jenis = $pending['jenis'];
        $valid = collect($pending['rows'])->filter(fn ($row) => $row['errors'] === []);
        $skipped = count($pending['rows']) - $valid->count();

        DB::transaction(function () use ($valid, $jenis): void {
            foreach ($valid as $row) {
                if ($jenis === 'pembelian') {
                    $this->recordPembelian($row['data']);
                } elseif ($jenis === 'penjualan') {
                    $this->recordPenjualan($row['data']);
                } else {
                    $this->recordStokAkhir($row['data']);
                }
            }
        });

        session()->forget('impor_import');
        if (! empty($pending['file']) && is_file($pending['file'])) {
            @unlink($pending['file']);
        }

        $message = $valid->count().' baris '.$pending['jenis'].' berhasil diimpor.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' baris bermasalah dilewati.';
        }

        return redirect()->route('impor.index', ['jenis' => $jenis])->with('success', $message);
    }

    private function resolvePemilik(string $kode): Pemilik
    {
        $kode = trim($kode);
        $pemilik = Pemilik::query()->where('kode', $kode)->lockForUpdate()->first();

        if (! $pemilik && ctype_digit($kode)) {
            $pemilik = Pemilik::query()
                ->whereRaw('CAST(kode AS UNSIGNED) = ?', [(int) $kode])
                ->lockForUpdate()
                ->first();
        }

        return $pemilik ?? Pemilik::query()->where('kode', $kode)->lockForUpdate()->firstOrFail();
    }

    private function recordPembelian(array $data): void
    {
        $namaBarang = trim($data['nama_barang']);
        $satuan = trim($data['satuan']);
        $pemilik = $this->resolvePemilik((string) $data['pemilik_kode']);
        $produk = Produk::query()->where('pemilik_id', $pemilik->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaBarang)])->lockForUpdate()->first();
        if (! $produk) {
            $produk = new Produk(['nama' => $namaBarang, 'pemilik_id' => $pemilik->id]);
            if ($satuan !== '') {
                $produk->satuan = $satuan;
            }
            $produk->save();
        }

        Pembelian::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => $data['tanggal'],
            'satuan' => $satuan,
            'jumlah' => (string) $data['jumlah'],
            'harga_beli' => (string) $data['harga_beli'],
            'total_harga' => FinancialAmount::multiply((string) $data['jumlah'], (string) $data['harga_beli']),
        ]);
        $produk->increment('stok', $data['jumlah']);
        $produk->harga_beli = $data['harga_beli'];
        $produk->save();
    }

    private function recordPenjualan(array $data): void
    {
        $namaBarang = trim($data['nama_barang']);
        $satuan = trim($data['satuan']);
        $pemilik = $this->resolvePemilik((string) $data['pemilik_kode']);
        $produk = Produk::query()->where('pemilik_id', $pemilik->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaBarang)])->lockForUpdate()->first();
        if (! $produk) {
            $produk = new Produk(['nama' => $namaBarang, 'pemilik_id' => $pemilik->id]);
            if ($satuan !== '') {
                $produk->satuan = $satuan;
            }
            $produk->save();
        }

        $jumlah = (string) $data['jumlah'];
        $hargaJual = (string) $data['harga_beli'];
        $hargaBeliSatuan = $produk->harga_beli ?? '0';

        Penjualan::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => $data['tanggal'],
            'satuan' => $satuan,
            'jumlah' => $jumlah,
            'harga_beli_satuan' => $hargaBeliSatuan,
            'harga_jual' => $hargaJual,
            'total_harga_beli' => FinancialAmount::multiply($jumlah, $hargaBeliSatuan),
            'total_harga_jual' => FinancialAmount::multiply($jumlah, $hargaJual),
            'laba' => FinancialAmount::multiply($jumlah, (string) bcsub($hargaJual, $hargaBeliSatuan, 2)),
        ]);
        $produk->decrement('stok', $jumlah);
        $produk->save();
    }

    private function recordStokAkhir(array $data): void
    {
        $namaBarang = trim($data['nama_barang']);
        $satuan = trim($data['satuan']);
        $kode = (string) $data['pemilik_kode'];
        $pemilik = $this->resolvePemilik($kode);
        $produk = Produk::query()->where('pemilik_id', $pemilik->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaBarang)])->lockForUpdate()->first();
        if (! $produk) {
            $produk = new Produk(['nama' => $namaBarang, 'pemilik_id' => $pemilik->id]);
            if ($satuan !== '') {
                $produk->satuan = $satuan;
            }
            $produk->save();
        }

        $produk->stok = $data['jumlah'];
        if ($satuan !== '') {
            $produk->satuan = $satuan;
        }
        $produk->save();
    }

    private static function parseStokAkhir(string $path, ?string $originalName = null): array
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            throw new RuntimeException('Stok akhir impor PDF belum didukung. Gunakan .xlsx, .xls, atau .csv.');
        }

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca. Pastikan formatnya .xlsx, .xls, atau .csv.');
        }

        $raw = $sheet->toArray(null, true, false, false);
        $defaultOwner = PembelianImport::detectOwnerName($raw);
        $tanggalKepala = PembelianImport::detectTanggalKepala($raw);
        $headerIndex = null;
        $columns = [];
        $headerCells = [];

        foreach ($raw as $index => $cells) {
            // Lewati baris identitas seperti "NAMA : Konco Tani" / "ALAMAT : ...".
            if (collect($cells)->contains(fn ($cell) => trim((string) $cell) === ':')) {
                continue;
            }

            $found = [];
            foreach ($cells as $col => $cell) {
                $field = self::matchStokAkhirHeader((string) $cell);
                if ($field !== null && ! isset($found[$field])) {
                    $found[$field] = $col;
                }
            }

            if (isset($found['nama_barang']) && (isset($found['stok_akhir']) || isset($found['stok_barang']))) {
                $headerIndex = $index;
                $columns = $found;
                $headerCells = $cells;
                break;
            }
            if (isset($found['nama_barang']) && count($found) >= 3) {
                $headerIndex = $index;
                $columns = $found;
                $headerCells = $cells;
                break;
            }
            if ($index >= 30) {
                break;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Baris judul kolom tidak ditemukan. Gunakan: Nama Barang, Berat Bersih, Stok Barang, Barang Terjual, Stok Akhir.');
        }

        if (! isset($columns['stok_akhir']) && ! isset($columns['stok_barang'])) {
            $judul = collect($headerCells)->filter(fn ($c) => trim((string) $c) !== '')->implode(' | ');

            throw new RuntimeException('Kolom STOK AKHIR tidak ditemukan. Judul kolom yang terbaca: '.$judul.'.');
        }

        // Kalau kolom STOK AKHIR kosong semua, pakai kolom STOK BARANG.
        if (isset($columns['stok_akhir']) && self::kolomKosong($raw, $headerIndex, $columns['stok_akhir'])) {
            unset($columns['stok_akhir']);
        }

        // Masih kosong? Cari kolom angka lain (mis. "3 Pcs" pada ISI/BOTOL).
        if (! isset($columns['stok_akhir']) && (! isset($columns['stok_barang']) || self::kolomKosong($raw, $headerIndex, $columns['stok_barang']))) {
            $pengganti = self::cariKolomAngka($raw, $headerIndex, $columns);
            if ($pengganti !== null) {
                $columns['stok_barang'] = $pengganti;
            }
        }

        // Header sering digabung (merge) beberapa kolom. Kumpulkan semua kolom
        // dalam rentang merge agar nilai stok tetap ketemu walau digeser.
        $rentang = [];
        foreach (['pemilik_kode', 'nama_pemilik', 'nama_barang', 'berat_bersih', 'stok_barang', 'barang_terjual', 'stok_akhir', 'keterangan'] as $field) {
            if (! isset($columns[$field])) {
                continue;
            }
            $rentang[$field] = [$columns[$field]];
        }

        if ($sheet->getMergeCells() !== []) {
            foreach (array_keys($sheet->getMergeCells()) as $range) {
                [$awal, $akhir] = array_pad(explode(':', $range), 2, $range);
                $baris = (int) preg_replace('/\D/', '', $awal);
                if ($baris !== $headerIndex + 1) {
                    continue;
                }
                $kolomAwal = Coordinate::columnIndexFromString(preg_replace('/\d/', '', $awal)) - 1;
                $kolomAkhir = Coordinate::columnIndexFromString(preg_replace('/\d/', '', $akhir)) - 1;

                foreach ($rentang as $field => $koloms) {
                    if (in_array($kolomAwal, $koloms, true) || in_array($kolomAkhir, $koloms, true)) {
                        $rentang[$field] = range($kolomAwal, $kolomAkhir);
                    }
                }
            }
        }

        $pemiliks = Pemilik::query()->get();
        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1, null, true) as $index => $cells) {
            $nilai = function (string $field) use ($cells, $columns, $rentang) {
                if (! isset($columns[$field])) {
                    return null;
                }

                $teks = trim((string) ($cells[$columns[$field]] ?? ''));
                if ($teks !== '') {
                    return $cells[$columns[$field]];
                }

                // Nilai bisa tergeser kalau sel judulnya di-merge.
                foreach ($rentang[$field] ?? [] as $kolom) {
                    $isi = trim((string) ($cells[$kolom] ?? ''));
                    if ($isi !== '') {
                        return $cells[$kolom];
                    }
                }

                return null;
            };

            $namaBarang = trim((string) $nilai('nama_barang'));
            if ($namaBarang === '') {
                continue; // baris kosong / baris nomor saja
            }

            $pemilikKodeRaw = $nilai('pemilik_kode');
            $pemilikKode = is_int($pemilikKodeRaw) || is_float($pemilikKodeRaw)
                ? str_pad((string) (int) $pemilikKodeRaw, 3, '0', STR_PAD_LEFT)
                : trim((string) ($pemilikKodeRaw ?? ''));
            if (ctype_digit($pemilikKode)) {
                $pemilikKode = str_pad($pemilikKode, 3, '0', STR_PAD_LEFT);
            }

            $namaPemilik = trim((string) ($nilai('nama_pemilik') ?? ''));
            if ($pemilikKode === '' && $namaPemilik === '' && $defaultOwner !== null) {
                $namaPemilik = $defaultOwner;
            }
            if ($pemilikKode === '' && $namaPemilik !== '') {
                $foundPemilik = PembelianImport::findPemilik($pemiliks, $namaPemilik);
                if ($foundPemilik) {
                    $pemilikKode = str_pad((string) $foundPemilik->kode, 3, '0', STR_PAD_LEFT);
                }
            }

            $stokBarangMentah = $nilai('stok_barang');
            $stokAkhirMentah = $nilai('stok_akhir');
            $terjualMentah = $nilai('barang_terjual');

            // Stok akhir dipakai kalau ada isinya, kalau tidak pakai stok barang.
            $stokMentah = trim((string) $stokAkhirMentah) !== '' ? $stokAkhirMentah : $stokBarangMentah;

            $satuan = '';
            $pisah = self::splitJumlahSatuan($stokMentah);
            if ($pisah !== null) {
                $satuan = $pisah[1];
            }

            $data = [
                'pemilik_kode' => $pemilikKode,
                'nama_pemilik' => $namaPemilik,
                'nama_barang' => $namaBarang,
                'tanggal' => $tanggalKepala ?? date('Y-m-d'),
                'berat_bersih' => trim((string) ($nilai('berat_bersih') ?? '')),
                'stok_barang' => self::normalizeNumber($stokBarangMentah),
                'barang_terjual' => self::normalizeNumber($terjualMentah),
                'stok_akhir' => self::normalizeNumber($stokMentah),
                'keterangan' => trim((string) ($nilai('keterangan') ?? '')),
                'satuan' => $satuan,
                'jumlah' => self::normalizeNumber($stokMentah),
                'harga_beli' => '0',
            ];

            $rules = [
                'pemilik_kode' => ['required'],
                'nama_barang' => ['required', 'string', 'max:150'],
                'jumlah' => ['required', 'numeric', 'min:0'],
            ];
            $messages = [
                'pemilik_kode.required' => $namaPemilik !== ''
                    ? 'Nama Pemilik "'.$namaPemilik.'" tidak ditemukan di Master ID Nama.'
                        .(($saran = PembelianImport::suggestPemilik($pemiliks, $namaPemilik)) ? ' Mungkin maksudnya "'.$saran.'".' : '')
                    : 'ID Pemilik atau Nama Pemilik wajib diisi dan cocok dengan Master ID Nama.',
                'nama_barang.required' => 'Nama Barang wajib diisi.',
                'nama_barang.max' => 'Nama Barang maksimal 150 karakter.',
                'jumlah.required' => 'Stok Akhir wajib diisi.',
                'jumlah.numeric' => 'Stok Akhir harus berupa angka.',
                'jumlah.min' => 'Stok Akhir tidak boleh negatif.',
            ];

            $validator = Validator::make($data, $rules, $messages);

            $errors = $validator->errors()->all();

            if ($errors === [] && $pemilikKode !== '') {
                $exists = $pemiliks->contains(function ($p) use ($pemilikKode) {
                    $kode = (string) $p->kode;

                    return $kode === $pemilikKode || str_pad($kode, 3, '0', STR_PAD_LEFT) === $pemilikKode;
                });

                if (! $exists) {
                    $errors[] = 'ID Pemilik '.$pemilikKode.' tidak ditemukan di Master ID Nama.';
                }
            }

            $rows[] = ['line' => $index + 1, 'data' => $data, 'errors' => $errors];
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada data di bawah baris judul.');
        }

        return $rows;
    }

    private static function normalizeNumber($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        return (string) floatval(str_replace(',', '.', (string) $value));
    }

    /** Cocokkan judul kolom khusus laporan stok akhir (format KUD). */
    private static function matchStokAkhirHeader(string $cell): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower($cell));

        if ($key === '') {
            return null;
        }

        if (str_contains($key, 'stokakhir') || str_contains($key, 'saldoakhir')) {
            return 'stok_akhir';
        }
        if (str_contains($key, 'stokbarang') || (str_contains($key, 'stok') && str_contains($key, 'barang'))) {
            return 'stok_barang';
        }
        if (str_contains($key, 'terjual') || str_contains($key, 'keluar')) {
            return 'barang_terjual';
        }
        if (str_contains($key, 'berat') || str_contains($key, 'isi') || str_contains($key, 'botol')) {
            return 'berat_bersih';
        }
        if (str_contains($key, 'keterangan') || $key === 'ket') {
            return 'keterangan';
        }
        if (str_contains($key, 'pemilikkode') || str_contains($key, 'idpemilik') || $key === 'id') {
            return 'pemilik_kode';
        }
        if (str_contains($key, 'pemilik')) {
            return 'nama_pemilik';
        }
        if (str_contains($key, 'tanggal') || str_contains($key, 'tgl')) {
            return 'tanggal';
        }
        if (str_contains($key, 'namabarang') || $key === 'nama' || $key === 'barang' || str_contains($key, 'produk')) {
            return 'nama_barang';
        }
        if (str_contains($key, 'stok') || str_contains($key, 'saldo')) {
            return 'stok_barang';
        }

        return null;
    }

    /** True bila semua sel pada kolom tersebut kosong di bawah baris judul. */
    private static function kolomKosong(array $raw, int $headerIndex, int $kolom): bool
    {
        foreach (array_slice($raw, $headerIndex + 1, null, true) as $cells) {
            if (trim((string) ($cells[$kolom] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Cari kolom berisi angka (mis. "3 Pcs") untuk dipakai sebagai stok. */
    private static function cariKolomAngka(array $raw, int $headerIndex, array $columns): ?int
    {
        $terlarang = array_filter([
            $columns['pemilik_kode'] ?? null,
            $columns['nama_pemilik'] ?? null,
            $columns['nama_barang'] ?? null,
        ], fn ($v) => $v !== null);

        $skor = [];
        foreach (array_slice($raw, $headerIndex + 1, null, true) as $cells) {
            foreach ($cells as $kolom => $sel) {
                if (in_array($kolom, $terlarang, true)) {
                    continue;
                }
                $teks = trim((string) $sel);
                if ($teks === '') {
                    continue;
                }
                if (preg_match('/^\d/', $teks)) {
                    // Angka yang menyatu dengan satuan ("3 Pcs") lebih diprioritaskan.
                    $skor[$kolom] = ($skor[$kolom] ?? 0) + (preg_match('/\d+\s*[A-Za-z]/', $teks) ? 2 : 1);
                }
            }
        }

        if ($skor === []) {
            return null;
        }

        arsort($skor);

        return (int) array_key_first($skor);
    }

    /** Pisahkan "3 Pcs" menjadi jumlah 3 dan satuan "Pcs". */
    private static function splitJumlahSatuan($value): ?array
    {
        $text = trim((string) $value);
        if ($text === '' || ! preg_match('/^(\d+(?:[.,]\d+)?)\s*([A-Za-z].*)$/u', $text, $m)) {
            return null;
        }

        return [str_replace(',', '.', $m[1]), trim($m[2])];
    }
}
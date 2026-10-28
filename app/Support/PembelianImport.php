<?php

namespace App\Support;

use App\Models\Pemilik;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class PembelianImport
{
    public const MAX_ROWS = 1000;

    /** Nama kolom resmi => alias header yang dikenali (huruf kecil, tanpa spasi/simbol). */
    private const HEADERS = [
        'pemilik_kode' => ['idpemilik', 'pemilikkode', 'kodepemilik', 'pemilik', 'id'],
        'nama_pemilik' => ['namapemilik', 'pemiliknama', 'namapemilik', 'pemiliknama'],
        'nama_barang' => ['namabarang', 'barang', 'nama', 'produk', 'namaproduk'],
        'tanggal' => ['tanggal', 'tgl', 'date', 'haritanggal'],
        'satuan' => ['satuan', 'unit', 'isibotol', 'isi', 'botol'],
        'jumlah' => ['jumlah', 'qty', 'kuantitas', 'masuk', 'keluar', 'terjual', 'barangterjual', 'stok', 'stokakhir', 'saldo', 'saldoakhir', 'volume'],
        'harga_beli' => ['hargabeli', 'harga', 'hargasatuan', 'hb', 'hargajual', 'hargasatuanjual'],
    ];

    /**
     * Baca file Excel/CSV/PDF dan kembalikan daftar baris.
     * Setiap row: ['line' => int, 'data' => array, 'errors' => string[]].
     */
    public static function parse(string $path, $pemiliks = null, ?string $originalName = null, string $jenis = 'pembelian'): array
    {
        $extension = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        $isPdf = $extension === 'pdf';

        if ($isPdf) {
            return self::parsePdf($path, $pemiliks);
        }

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca. Pastikan formatnya .xlsx, .xls, .csv, atau .pdf.');
        }

        $raw = $sheet->toArray(null, true, false, false);
        $defaultOwner = self::detectOwnerName($raw);
        $defaultTanggal = self::detectTanggalKepala($raw);

        $headerIndex = null;
        $columns = [];
        $headerCells = [];
        $kandidat = [];
        $kandidatSkor = 0;

        foreach ($raw as $index => $cells) {
            // Lewati baris identitas seperti "NAMA : Konco Tani" / "ALAMAT : ...".
            if (collect($cells)->contains(fn ($cell) => trim((string) $cell) === ':')) {
                continue;
            }

            $found = self::mapHeader($cells);
            $adaIsi = collect($cells)->contains(fn ($cell) => trim((string) $cell) !== '');
            if ($adaIsi && ($kandidat === [] || count($found) > $kandidatSkor)) {
                $kandidatSkor = count($found);
                $kandidat = $cells;
            }
            if (self::isHeaderRow($found)) {
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
            $judul = collect($kandidat)
                ->map(fn ($cell) => trim((string) $cell))
                ->filter()
                ->implode(' | ');

            $petunjuk = $judul !== ''
                ? ' Judul kolom yang terbaca: '.mb_substr($judul, 0, 200).'.'
                : '';

            throw new RuntimeException('Baris judul kolom tidak ditemukan. Gunakan template: ID Pemilik (atau Nama Pemilik), Nama Barang, Tanggal, Satuan, Jumlah, Harga Beli.'.$petunjuk);
        }

        // Kolom jumlah yang benar tergantung jenis data:
        // penjualan -> BARANG TERJUAL / KELUAR, pembelian -> MASUK / JUMLAH.
        $prioritas = $jenis === 'penjualan'
            ? ['terjual', 'keluar', 'banyak', 'jumlah', 'qty']
            : ['masuk', 'jumlah', 'qty', 'volume'];

        $kolomJumlah = self::pilihKolom($headerCells, $prioritas);
        if ($kolomJumlah !== null) {
            $columns['jumlah'] = $kolomJumlah;
        }

        $pemiliks = $pemiliks ?? Pemilik::query()->get();
        $rows = [];

        foreach (array_slice($raw, $headerIndex + 1, null, true) as $index => $cells) {
            $values = [];
            foreach (array_keys(self::HEADERS) as $field) {
                $values[$field] = isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;
            }
            if (collect($values)->every(fn ($v) => $v === null || trim((string) $v) === '')) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('Maksimal '.self::MAX_ROWS.' baris per impor.');
            }

            $rows[] = self::normalizeRow($values, $index + 1, $pemiliks, $defaultOwner, $defaultTanggal);
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada data di bawah baris judul.');
        }

        return $rows;
    }

    public static function writeTemplate(string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pembelian');
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode('@');
        $sheet->fromArray([
            ['ID Pemilik', 'Nama Barang', 'Tanggal', 'Satuan', 'Jumlah', 'Harga Beli'],
            ['001', 'Pupuk Urea', '2026-10-22', 'kg', 10, 12500],
        ], null, 'A1', true);
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($path);
    }

    /**
     * Parse PDF berisi teks menjadi baris siap validasi.
     */
    private static function parsePdf(string $path, $pemiliks = null): array
    {
        $pemiliks = $pemiliks ?? Pemilik::query()->get();
        $rows = [];

        foreach (self::readPdfRows($path) as $row) {
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('Maksimal '.self::MAX_ROWS.' baris per impor.');
            }

            $rows[] = self::normalizeRow($row['values'], $row['line'], $pemiliks);
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada data yang terbaca di PDF.');
        }

        return $rows;
    }

    /**
     * Ekstrak baris data dari file PDF.
     * Mengembalikan array ['values' => array 6 kolom, 'line' => int].
     */
    private static function readPdfRows(string $path): array
    {
        try {
            $parser = new Parser();
            $document = $parser->parseFile($path);
            $text = $document instanceof \Stringable ? (string) $document : $document->getText();
        } catch (Throwable $e) {
            throw new RuntimeException('PDF tidak dapat dibaca. Pastikan file PDF berisi teks (bukan hasil scan/gambar).');
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $rows = [];
        $lineNo = 0;

        foreach ($lines as $line) {
            $lineNo++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $flat = preg_replace('/[^a-z0-9]/', '', mb_strtolower($line));
            if (self::looksLikePdfHeader($flat)) {
                continue;
            }

            $values = self::parsePdfDataLine($line);
            if ($values === null) {
                continue;
            }

            $rows[] = ['values' => $values, 'line' => $lineNo];
        }

        if ($rows === []) {
            throw new RuntimeException('Tidak ada baris data yang terbaca di PDF. Pastikan isi PDF memuat kolom: ID Pemilik, Nama Barang, Tanggal, Satuan, Jumlah, Harga Beli.');
        }

        return $rows;
    }

    /** Deteksi baris judul kolom pada PDF (bukan data). */
    private static function looksLikePdfHeader(string $flat): bool
    {
        $hasTanggal = str_contains($flat, 'tanggal') || str_contains($flat, 'tgl');
        $hasJumlah = str_contains($flat, 'jumlah') || str_contains($flat, 'qty');
        $hasHarga = str_contains($flat, 'harga') || str_contains($flat, 'hargabeli');
        $hasSatuan = str_contains($flat, 'satuan');

        return $hasTanggal && $hasJumlah && $hasHarga && $hasSatuan;
    }

    /**
     * Ambil 6 nilai dari satu baris teks PDF.
     * Mengembalikan null bila baris tidak cocok pola.
     */
    private static function parsePdfDataLine(string $line): ?array
    {
        // Satuan opsional: beberapa file tidak menyertakan kolom satuan sama sekali.
        $pattern = '/^\s*(?<kode>\d{3,10})\s+(?<nama>\S.*?)\s+(?<tanggal>\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})\s+(?:(?<satuan>[A-Za-z][A-Za-z0-9\/\.]*)\s+)?(?<jumlah>\d+(?:[.,]\d+)?)\s+(?<harga>\d+(?:[.,]\d+)?)\s*$/u';

        if (! preg_match($pattern, $line, $m)) {
            return null;
        }

        return [
            'pemilik_kode' => $m['kode'],
            'nama_barang' => trim($m['nama']),
            'tanggal' => trim($m['tanggal']),
            'satuan' => trim((string) ($m['satuan'] ?? '')),
            'jumlah' => trim($m['jumlah']),
            'harga_beli' => trim($m['harga']),
        ];
    }

    /**
     * Bandingkan nama pemilik dengan toleransi: spasi ganda, non-breaking space,
     * huruf besar/kecil, dan tanda baca.
     */
    public static function normalizeName(?string $name): string
    {
        $name = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}", "\t"], ' ', (string) $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? (string) $name;
        $name = preg_replace('/[^\p{L}\p{N} ]/u', '', $name) ?? $name;

        return mb_strtolower(trim($name));
    }

    /** Cari pemilik berdasarkan nama (toleran spasi/huruf/tanda baca). */
    public static function findPemilik($pemiliks, string $nama)
    {
        $target = self::normalizeName($nama);
        if ($target === '') {
            return null;
        }

        $ketat = str_replace(' ', '', $target);

        foreach ($pemiliks as $pemilik) {
            if (self::normalizeName((string) $pemilik->nama) === $target) {
                return $pemilik;
            }
        }

        foreach ($pemiliks as $pemilik) {
            if (str_replace(' ', '', self::normalizeName((string) $pemilik->nama)) === $ketat) {
                return $pemilik;
            }
        }

        // Salah ketik sedikit (mis. "Konco Tani" vs "Konco Tanii").
        foreach ($pemiliks as $pemilik) {
            $namaPemilik = self::normalizeName((string) $pemilik->nama);
            $jarak = levenshtein($ketat, str_replace(' ', '', $namaPemilik));
            if ($jarak <= 2 && mb_strlen($ketat) > 4) {
                return $pemilik;
            }
        }

        return null;
    }

    /** Nama pemilik terdekat untuk saran pesan error. */
    public static function suggestPemilik($pemiliks, string $nama): ?string
    {
        $target = str_replace(' ', '', self::normalizeName($nama));
        if ($target === '') {
            return null;
        }

        $terbaik = null;
        $jarakTerbaik = PHP_INT_MAX;

        foreach ($pemiliks as $pemilik) {
            $jarak = levenshtein($target, str_replace(' ', '', self::normalizeName((string) $pemilik->nama)));
            if ($jarak < $jarakTerbaik) {
                $jarakTerbaik = $jarak;
                $terbaik = (string) $pemilik->nama;
            }
        }

        return $jarakTerbaik <= 5 ? $terbaik : null;
    }

    /** Cocokkan judul kolom, termasuk judul panjang seperti "STOK AKHIR (PCS)". */
    public static function matchHeaderKey(string $cell): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower($cell));

        if ($key === '') {
            return null;
        }

        foreach (self::HEADERS as $field => $aliases) {
            if (in_array($key, $aliases, true)) {
                return $field;
            }
        }

        if (str_contains($key, 'pemilikkode') || str_contains($key, 'idpemilik') || str_contains($key, 'kodepemilik')) {
            return 'pemilik_kode';
        }
        if (str_contains($key, 'pemilik')) {
            return 'nama_pemilik';
        }
        if (str_contains($key, 'terjual') || str_contains($key, 'keluar')) {
            return 'jumlah';
        }
        if (str_contains($key, 'masuk')) {
            return 'jumlah';
        }
        if (str_contains($key, 'stok') || str_contains($key, 'saldo')) {
            return 'jumlah';
        }
        if (str_contains($key, 'barang') || str_contains($key, 'produk')) {
            return 'nama_barang';
        }
        if (str_contains($key, 'tanggal') || str_contains($key, 'tgl')) {
            return 'tanggal';
        }
        if (str_contains($key, 'harga')) {
            return 'harga_beli';
        }
        if (str_contains($key, 'satuan') || str_contains($key, 'unit') || str_contains($key, 'botol') || str_contains($key, 'isi')) {
            return 'satuan';
        }
        if (str_contains($key, 'jumlah') || str_contains($key, 'qty') || str_contains($key, 'jml') || str_contains($key, 'banyak')) {
            return 'jumlah';
        }

        return null;
    }

    /**
     * Cari nama pemilik di kepala file (format lama), mis. "NAMA : Konco Tani".
     */
    public static function detectOwnerName(array $raw, int $limit = 30): ?string
    {
        $labels = ['nama', 'namapemilik', 'pemilik'];

        foreach (array_slice($raw, 0, $limit, true) as $cells) {
            foreach ($cells as $col => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $cell));
                if (! in_array($key, $labels, true)) {
                    continue;
                }

                for ($i = $col + 1; $i < count($cells); $i++) {
                    $value = trim((string) ($cells[$i] ?? ''));
                    if ($value === '' || preg_match('/^[^a-z0-9]+$/i', $value)) {
                        continue;
                    }

                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Ambil tanggal dari kepala file, mis. "HARI, TANGGAL : Sabtu, 03 Oktober 2026".
     * Mengembalikan Y-m-d atau null bila tidak ditemukan.
     */
    public static function detectTanggalKepala(array $raw, int $limit = 30): ?string
    {
        $bulan = [
            'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
            'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
            'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
        ];

        foreach (array_slice($raw, 0, $limit, true) as $cells) {
            $punyaLabelTanggal = false;
            foreach ($cells as $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $cell));
                if (str_contains($key, 'tanggal')) {
                    $punyaLabelTanggal = true;
                    break;
                }
            }
            if (! $punyaLabelTanggal) {
                continue;
            }

            foreach ($cells as $cell) {
                $teks = trim((string) $cell);
                if ($teks === '' || preg_match('/^[^a-z0-9]+$/i', $teks)) {
                    continue;
                }

                $teks = preg_replace('/^[a-z]+,\s*/i', '', $teks); // buang "Sabtu,"
                if (! preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/', $teks, $m)) {
                    continue;
                }
                $namaBulan = $bulan[mb_strtolower($m[2])] ?? null;
                if ($namaBulan === null) {
                    continue;
                }

                return $m[3].'-'.$namaBulan.'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    /** Pilih kolom pertama yang judulnya memuat salah satu kata kunci (berurutan prioritas). */
    public static function pilihKolom(array $headerCells, array $prioritas): ?int
    {
        foreach ($prioritas as $kunci) {
            foreach ($headerCells as $col => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $cell));
                if ($key !== '' && str_contains($key, $kunci)) {
                    return $col;
                }
            }
        }

        return null;
    }

    /** Baris dianggap judul kolom bila memuat Nama Barang + salah satu kolom kunci lain. */
    private static function isHeaderRow(array $found): bool
    {
        if (count($found) >= 5) {
            return true;
        }

        if (isset($found['nama_barang']) && count($found) >= 3) {
            return true;
        }

        return isset($found['nama_barang'])
            && (isset($found['jumlah']) || isset($found['pemilik_kode']) || isset($found['nama_pemilik']) || isset($found['harga_beli']));
    }

    private static function mapHeader(array $cells): array
    {
        $found = [];
        $skor = [];

        foreach ($cells as $col => $cell) {
            $field = self::matchHeaderKey((string) $cell);
            if ($field === null) {
                continue;
            }

            $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $cell));

            // Utamakan judul yang eksplisit (mis. "NAMA PRODUK" menang atas "KODE BARANG").
            $nilai = 1;
            if ($field === 'nama_barang' && str_contains($key, 'nama')) {
                $nilai = 2;
            }
            if ($field === 'jumlah' && (str_contains($key, 'jumlah') || str_contains($key, 'qty') || str_contains($key, 'jml'))) {
                $nilai = 2;
            }

            if (! isset($found[$field]) || $nilai > $skor[$field]) {
                $found[$field] = $col;
                $skor[$field] = $nilai;
            }
        }

        return $found;
    }

    private static function normalizeRow(array $values, int $line, $pemiliks, ?string $defaultNamaPemilik = null, ?string $defaultTanggal = null): array
    {
        $pemilikKodeRaw = $values['pemilik_kode'] ?? null;
        $pemilikKode = self::normalizeKode($pemilikKodeRaw);
        $namaPemilik = trim((string) ($values['nama_pemilik'] ?? ''));

        // Format lama: nama pemilik ada di kepala file, bukan di kolom.
        if ($namaPemilik === '' && $defaultNamaPemilik !== null) {
            $namaPemilik = $defaultNamaPemilik;
        }

        // Jika ID kosong tapi Nama Pemilik diisi, cari kode pemilik via nama.
        if ($pemilikKode === '' && $namaPemilik !== '') {
            $found = self::findPemilik($pemiliks, $namaPemilik);
            if ($found) {
                $pemilikKode = str_pad((string) $found->kode, 3, '0', STR_PAD_LEFT);
            }
        } elseif ($pemilikKode === '' && $pemilikKodeRaw !== null) {
            // Kadang nama dimasukkan di kolom ID Pemilik
            $namaAsKode = trim((string) $pemilikKodeRaw);
            if ($namaAsKode !== '' && ! ctype_digit(str_replace([' ', '-', '_'], '', $namaAsKode))) {
                $found = self::findPemilik($pemiliks, $namaAsKode);
                if ($found) {
                    $pemilikKode = str_pad((string) $found->kode, 3, '0', STR_PAD_LEFT);
                    $namaPemilik = $found->nama;
                }
            }
        } elseif ($pemilikKode !== '' && ! preg_match('/^\d{3,10}$/', $pemilikKode)) {
            // Jika berisi nama, coba cari
            $found = self::findPemilik($pemiliks, (string) $pemilikKodeRaw);
            if ($found) {
                $pemilikKode = str_pad((string) $found->kode, 3, '0', STR_PAD_LEFT);
            }
        }

        $data = [
            'pemilik_kode' => $pemilikKode,
            'nama_barang' => trim((string) $values['nama_barang']),
            'tanggal' => self::normalizeDate($values['tanggal']) ?: (string) $defaultTanggal,
            'satuan' => trim((string) $values['satuan']),
            'jumlah' => self::normalizeNumber($values['jumlah']),
            'harga_beli' => self::normalizeNumber($values['harga_beli']),
        ];

        // Kolom ISI BOTOL berisi "1 Pcs": angka dipakai bila Jumlah kosong,
        // teks satuan tetap dipakai tanpa awalan angka.
        if ($data['satuan'] !== '' && preg_match('/^(\d+(?:[.,]\d+)?)\s*(.+)$/', $data['satuan'], $m)) {
            if ($data['jumlah'] === '') {
                $data['jumlah'] = str_replace(',', '.', $m[1]);
            }
            $data['satuan'] = trim($m[2]);
        }

        $rules = [
            'nama_barang' => ['required', 'string', 'max:150'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'jumlah' => ['required', 'numeric', 'regex:/^\\d{1,6}(?:\\.\\d{1,3})?$/', 'gt:0'],
            'harga_beli' => ['required', 'numeric', 'regex:/^\\d{1,7}(?:\\.\\d{1,2})?$/', 'gt:0'],
        ];

        $messages = [
            'nama_barang.required' => 'Nama Barang wajib diisi.',
            'nama_barang.max' => 'Nama Barang maksimal 150 karakter.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'satuan.max' => 'Satuan maksimal 30 karakter.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.gt' => 'Jumlah harus lebih besar dari 0.',
            'jumlah.regex' => 'Format Jumlah tidak valid.',
            'harga_beli.required' => 'Harga Beli wajib diisi.',
            'harga_beli.numeric' => 'Harga Beli harus berupa angka.',
            'harga_beli.gt' => 'Harga Beli harus lebih besar dari 0.',
            'harga_beli.regex' => 'Format Harga Beli tidak valid.',
        ];

        $attributes = [
            'nama_barang' => 'Nama Barang',
            'tanggal' => 'Tanggal',
            'satuan' => 'Satuan',
            'jumlah' => 'Jumlah',
            'harga_beli' => 'Harga Beli',
        ];

        // ID Pemilik wajib diisi (via ID atau Nama Pemilik yang cocok).
        if ($pemilikKode === '') {
            $rules['pemilik_kode'] = ['required'];
            $saran = self::suggestPemilik($pemiliks, $namaPemilik);
            $messages['pemilik_kode.required'] = $namaPemilik !== ''
                ? 'Nama Pemilik "'.$namaPemilik.'" tidak ditemukan di Master ID Nama.'
                    .($saran ? ' Mungkin maksudnya "'.$saran.'".' : '')
                : 'ID Pemilik atau Nama Pemilik wajib diisi dan cocok dengan Master ID Nama.';
            $attributes['pemilik_kode'] = 'ID Pemilik / Nama Pemilik';
        } else {
            $rules['pemilik_kode'] = ['required', 'regex:/^\\d{3,10}$/'];
            $messages['pemilik_kode.required'] = 'ID Pemilik wajib diisi.';
            $messages['pemilik_kode.regex'] = 'ID Pemilik harus berupa angka.';
            $attributes['pemilik_kode'] = 'ID Pemilik';
        }

        $validator = Validator::make($data, $rules, $messages, $attributes);

        $errors = $validator->errors()->all();

        if (! $validator->errors()->has('pemilik_kode') && $pemilikKode !== '') {
            $kodeList = $pemiliks->pluck('kode')->all();
            if (! in_array($data['pemilik_kode'], $kodeList, true)) {
                $errors[] = 'ID Pemilik '.$data['pemilik_kode'].' tidak ditemukan di Master ID Nama.';
            }
        }

        return ['line' => $line, 'data' => $data, 'errors' => $errors];
    }

    private static function normalizeKode(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $text = (string) (int) $value;
        } else {
            $text = trim((string) $value);
        }

        return ctype_digit($text) ? str_pad($text, 3, '0', STR_PAD_LEFT) : $text;
    }

    private static function normalizeDate(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable $e) {
                return trim((string) $value);
            }
        }

        $text = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd-m-y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (Throwable $e) {
                continue;
            }
            if ($date && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return $text;
    }

    private static function normalizeNumber(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $text = number_format((float) $value, 3, '.', '');

            return rtrim(rtrim($text, '0'), '.');
        }

        $text = preg_replace('/[^\d.,]/', '', (string) $value);
        if ($text === '') {
            return '';
        }
        if (str_contains($text, ',')) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            $text = str_replace('.', '', $text);
        }

        return $text;
    }
}

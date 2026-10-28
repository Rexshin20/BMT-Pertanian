<?php

namespace App\Http\Controllers;

use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LaporanStokAkhirController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pemilik' => ['nullable', 'string', 'exists:pemiliks,kode'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ]);

        $tanggalMulai = $filters['tanggal_mulai'] ?? Carbon::today()->startOfMonth()->toDateString();
        $tanggalSelesai = $filters['tanggal_selesai'] ?? Carbon::today()->toDateString();

        $terjualPeriode = $this->totalTerjualPeriode($tanggalMulai, $tanggalSelesai);
        $terjualSetelah = $this->totalTerjualSetelah($tanggalSelesai);

        $produkQuery = Produk::query()->with('pemilik');

        if (! empty($filters['pemilik'])) {
            $produkQuery->whereHas('pemilik', fn ($ownerQuery) => $ownerQuery->where('kode', $filters['pemilik']));
        }

        $laporans = $produkQuery->get()
            ->map(function (Produk $produk) use ($terjualPeriode, $terjualSetelah) {
                $penjualan = (float) ($terjualPeriode[$produk->id] ?? 0);
                // Stok sekarang adalah stok akhir pada tanggal selesai; penjualan
                // setelah tanggal selesai sudah menguranginya, jadi stok akhir
                // periode harus menambahkannya kembali.
                $stokAkhir = (float) $produk->stok + (float) ($terjualSetelah[$produk->id] ?? 0);

                // Pisahkan ukuran/berat bersih dari nama barang jika tertera di nama (misal: "AMINAX 400 ML" -> Nama: "AMINAX", Berat: "400 ML")
                $nama = trim($produk->nama);
                $beratBersih = '';
                if (preg_match('/^(.*?)(?:\s*\(|\s+)(\d+(?:[.,]\d+)?\s*(?:ml|liter|lt|ltr|kg|gram|gr|ons|dus|zak|pack|btl|botol|sachet|sak|can|galon))\)?$/i', $nama, $matches)) {
                    $namaBarang = trim($matches[1]);
                    $beratBersih = trim($matches[2]);
                } else {
                    $namaBarang = $nama;
                }

                // Kalau ukuran tidak ditulis di nama barang, pakai satuan yang mengandung
                // ukuran (mis. "200 ml") supaya kolom Berat Bersih tidak kosong.
                if ($beratBersih === '') {
                    $beratBersih = self::beratBersihDariSatuan((string) $produk->satuan);
                }

                return (object) [
                    'produk' => $produk,
                    'nama_barang' => $namaBarang,
                    'berat_bersih' => $beratBersih,
                    'stok_awal' => $stokAkhir + $penjualan,
                    'penjualan' => $penjualan,
                    'stok_akhir' => $stokAkhir,
                ];
            })
            // Hanya tampilkan produk yang relevan: ada stok awal ATAU ada penjualan di periode ini.
            // Produk dengan semua nilai 0 (tidak aktif di periode ini) disembunyikan.
            ->filter(fn ($laporan) => $laporan->stok_awal > 0 || $laporan->penjualan > 0)
            ->values();

        // "Semua nama": barang yang sama (nama + berat bersih) dari pemilik berbeda
        // digabung jadi satu baris (stok & penjualan dijumlah) biar tabel tidak boros.
        if (empty($filters['pemilik'])) {
            $laporans = $laporans
                ->groupBy(fn ($laporan) => mb_strtolower(trim($laporan->nama_barang)).'|'.mb_strtolower(trim($laporan->berat_bersih)))
                ->map(fn ($group) => (object) [
                    'nama_barang' => $group->first()->nama_barang,
                    'berat_bersih' => $group->first()->berat_bersih,
                    'stok_awal' => $group->sum('stok_awal'),
                    'penjualan' => $group->sum('penjualan'),
                    'stok_akhir' => $group->sum('stok_akhir'),
                ])
                ->values();
        }

        $pemilikTerpilihObj = ! empty($filters['pemilik'])
            ? Pemilik::query()->where('kode', $filters['pemilik'])->first()
            : null;

        $alamat = trim((string) ($filters['alamat'] ?? ''));
        if ($alamat === '') {
            $alamat = trim((string) ($pemilikTerpilihObj?->alamat ?? ''));
        }

        $tanggalFormatted = Carbon::parse($tanggalSelesai)->locale('id')->translatedFormat('l, d F Y');

        return view('keuangan.laporan.stok-akhir', [
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'tanggalFormatted' => $tanggalFormatted,
            'laporans' => $laporans,
            'totalStokAwal' => $laporans->sum('stok_awal'),
            'totalPenjualan' => $laporans->sum('penjualan'),
            'totalStokAkhir' => $laporans->sum('stok_akhir'),
            'pemiliks' => Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get(),
            'pemilikTerpilih' => $filters['pemilik'] ?? '',
            'pemilikTerpilihNama' => $pemilikTerpilihObj?->nama,
            'pemilikTerpilihObj' => $pemilikTerpilihObj,
            'alamat' => $alamat,
        ]);
    }

    /**
     * Ambil ukuran dari satuan bila satuannya berupa ukuran kemasan (mis. "200 ml", "1 kg").
     * Satuan hitung biasa seperti "pcs" atau "botol" tidak dipakai sebagai berat bersih.
     */
    private static function beratBersihDariSatuan(string $satuan): string
    {
        $satuan = trim($satuan);

        if ($satuan !== '' && preg_match('/\d+(?:[.,]\d+)?\s*(?:ml|liter|lt|ltr|kg|gram|gr|ons|dus|zak|pack|btl|botol|sachet|sak|can|galon)\b/i', $satuan)) {
            return $satuan;
        }

        return '';
    }

    /**
     * Total jumlah terjual per produk_id pada rentang tanggal berjalan.
     */
    private function totalTerjualPeriode(string $tanggalMulai, string $tanggalSelesai): Collection
    {
        return Penjualan::query()
            ->whereDate('tanggal', '>=', $tanggalMulai)
            ->whereDate('tanggal', '<=', $tanggalSelesai)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, COALESCE(SUM(jumlah), 0) AS total_jumlah')
            ->pluck('total_jumlah', 'produk_id');
    }

    /**
     * Total jumlah terjual per produk_id setelah tanggal selesai.
     */
    private function totalTerjualSetelah(string $tanggalSelesai): Collection
    {
        return Penjualan::query()
            ->whereDate('tanggal', '>', $tanggalSelesai)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, COALESCE(SUM(jumlah), 0) AS total_jumlah')
            ->pluck('total_jumlah', 'produk_id');
    }
}

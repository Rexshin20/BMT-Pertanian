<?php

namespace App\Http\Controllers;

use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Support\FinancialAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PenjualanController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pemilik' => ['nullable', 'string', 'exists:pemiliks,kode'],
        ]);
        $query = Penjualan::query()->with('produk.pemilik');

        if (! empty($filters['pemilik'])) {
            $query->whereHas('produk.pemilik', fn ($ownerQuery) => $ownerQuery->where('kode', $filters['pemilik']));
        }

        $penjualans = $query
            ->latest('tanggal')
            ->latest('id')
            ->get()
            ->map(fn (Penjualan $p) => (object) [
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_jual,
                'jumlah' => $p->jumlah,
                'ownerKode' => $p->produk->pemilik->kode ?? '',
            ]);

        // "Semua nama": transaksi yang tanggal, barang, satuan, dan harganya sama
        // digabung jadi satu baris (jumlah dijumlah) biar tabel tidak boros.
        if (empty($filters['pemilik'])) {
            $penjualans = $penjualans
                ->groupBy(fn ($p) => $p->tanggal->format('Y-m-d')
                    .'|'.mb_strtolower($p->nama_barang)
                    .'|'.mb_strtolower($p->satuan)
                    .'|'.sprintf('%.4f', (float) $p->harga))
                ->map(fn ($group) => $group->count() === 1
                    ? $group->first()
                    : (object) [
                        'tanggal' => $group->first()->tanggal,
                        'nama_barang' => $group->first()->nama_barang,
                        'satuan' => $group->first()->satuan,
                        'harga' => $group->first()->harga,
                        'jumlah' => (string) $group->sum('jumlah'),
                        'ownerKode' => '',
                    ])
                ->values();
        }

        return view('keuangan.penjualan.master-data', [
            'penjualans' => $penjualans,
            'pemiliks' => Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get(),
            'pemilikTerpilih' => $filters['pemilik'] ?? '',
            'tanggalMulai' => $filters['tanggal_mulai'] ?? '',
            'tanggalSelesai' => $filters['tanggal_selesai'] ?? '',
            'pemilikTerpilihNama' => ! empty($filters['pemilik'])
                ? Pemilik::query()->where('kode', $filters['pemilik'])->value('nama')
                : null,
            'pemilikTerpilihObj' => ! empty($filters['pemilik'])
                ? Pemilik::query()->where('kode', $filters['pemilik'])->first()
                : null,
        ]);
    }

    public function create(): View
    {
        $pemiliks = Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get();
        $lastKode = session('last_pemilik_kode');

        return view('keuangan.penjualan.create', [
            'nomorBerikutnya' => Penjualan::query()->count() + 1,
            'pemiliks' => $pemiliks,
            'lastPemilikKode' => $pemiliks->contains('kode', $lastKode) ? $lastKode : null,
            'produks' => Produk::query()
                ->where('stok', '>', 0)
                ->with('pemilik')
                ->orderBy('nama')
                ->get(['id', 'pemilik_id', 'nama', 'satuan', 'stok']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pemilik_kode' => ['required', 'string', 'regex:/^\d{3,10}$/', 'exists:pemiliks,kode'],
            'produk_id' => ['required', 'integer', 'exists:produks,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'satuan' => ['required', 'string', 'max:30'],
            'jumlah' => ['required', 'numeric', 'regex:/^\d{1,6}(?:\.\d{1,3})?$/', 'gt:0'],
            'harga_jual' => ['required', 'numeric', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'gt:0'],
        ]);

        DB::transaction(function () use ($data): void {
            $pemilik = Pemilik::query()
                ->where('kode', $data['pemilik_kode'])
                ->lockForUpdate()
                ->firstOrFail();
            $produk = Produk::query()
                ->whereKey($data['produk_id'])
                ->where('pemilik_id', $pemilik->id)
                ->lockForUpdate()
                ->first();

            if (! $produk) {
                throw ValidationException::withMessages([
                    'produk_id' => 'Barang tidak ditemukan. Pilih barang yang tersedia dari daftar.',
                ]);
            }

            $jumlah = (string) $data['jumlah'];

            if (FinancialAmount::quantityInThousandths($jumlah) > FinancialAmount::quantityInThousandths((string) $produk->stok)) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Stok tidak cukup. Stok tersedia: '.$produk->stok.' '.$produk->satuan.'.',
                ]);
            }

            $hargaBeli = (string) $produk->harga_beli;
            $hargaJual = (string) $data['harga_jual'];
            $satuan = trim($data['satuan']);
            $totalHargaBeli = FinancialAmount::multiply($jumlah, $hargaBeli);
            $totalHargaJual = FinancialAmount::multiply($jumlah, $hargaJual);

            Penjualan::query()->create([
                'produk_id' => $produk->id,
                'tanggal' => $data['tanggal'],
                'satuan' => $satuan,
                'jumlah' => $jumlah,
                'harga_beli_satuan' => $hargaBeli,
                'harga_jual' => $hargaJual,
                'total_harga_beli' => $totalHargaBeli,
                'total_harga_jual' => $totalHargaJual,
                'laba' => FinancialAmount::profit($jumlah, $hargaJual, $hargaBeli),
            ]);

            $produk->decrement('stok', $jumlah);
            $produk->harga_jual = $hargaJual;
            $produk->save();
        });

        session(['last_pemilik_kode' => $data['pemilik_kode']]);

        return redirect()->route('penjualan.create')->with('success', 'Penjualan dicatat dan stok barang berkurang.');
    }
}

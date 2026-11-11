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
                'id' => $p->id,
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_jual,
                'jumlah' => $p->jumlah,
                'ownerKode' => $p->produk->pemilik->kode ?? '',
            ]);

        if (empty($filters['pemilik'])) {
            $penjualans = $penjualans
                ->groupBy(fn ($p) => $p->tanggal->format('Y-m-d')
                    .'|'.mb_strtolower(trim($p->nama_barang))
                    .'|'.Produk::normalizeSatuan($p->satuan)
                    .'|'.sprintf('%.4f', (float) $p->harga))
                ->map(fn ($group) => $group->count() === 1
                    ? $group->first()
                    : (object) [
                        'id' => $group->first()->id,
                        'tanggal' => $group->first()->tanggal,
                        'nama_barang' => $group->first()->nama_barang,
                        'satuan' => $group->first()->satuan,
                        'harga' => $group->first()->harga,
                        'jumlah' => FinancialAmount::quantityFromThousandths(
                            $group->sum(fn ($p) => FinancialAmount::quantityInThousandths((string) $p->jumlah))
                        ),
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

    public function edit(Penjualan $penjualan): View
    {
        return view('keuangan.transaksi.edit', [
            'jenisTransaksi' => 'Penjualan',
            'transaksi' => $penjualan->load('produk.pemilik'),
            'hargaField' => 'harga_jual',
            'hargaLabel' => 'Harga jual per unit',
            'updateRoute' => 'penjualan.update',
            'backRoute' => 'penjualan.create.list',
        ]);
    }

    public function update(Request $request, Penjualan $penjualan): RedirectResponse
    {
        $data = $request->validate([
            'produk_id' => ['required', 'integer', 'exists:produks,id'],
            'satuan' => ['required', 'string', 'max:30'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jumlah' => ['required', 'numeric', 'regex:/^\d{1,6}(?:\.\d{1,3})?$/', 'gt:0'],
            'harga_jual' => ['required', 'numeric', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'gt:0'],
        ]);

        DB::transaction(function () use ($data, $penjualan): void {
            $lockedPenjualan = Penjualan::query()->whereKey($penjualan->id)->lockForUpdate()->firstOrFail();
            $produkIds = collect([$lockedPenjualan->produk_id, (int) $data['produk_id']])->unique()->sort()->values();
            $produks = Produk::query()->whereIn('id', $produkIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $produkLama = $produks->get($lockedPenjualan->produk_id);
            $produkBaru = $produks->get((int) $data['produk_id']);
            $jumlahLama = FinancialAmount::quantityInThousandths((string) $lockedPenjualan->jumlah);
            $jumlahBaru = FinancialAmount::quantityInThousandths((string) $data['jumlah']);

            if (! $produkLama || ! $produkBaru) {
                throw ValidationException::withMessages([
                    'produk_id' => 'Barang yang dipilih tidak ditemukan.',
                ]);
            }

            if ($lockedPenjualan->produk_id === (int) $data['produk_id']) {
                $stokBaru = FinancialAmount::quantityInThousandths((string) $produkLama->stok) + $jumlahLama - $jumlahBaru;
            } else {
                $stokLama = FinancialAmount::quantityInThousandths((string) $produkLama->stok) + $jumlahLama;
                $stokTersediaBaru = FinancialAmount::quantityInThousandths((string) $produkBaru->stok);
                if ($stokTersediaBaru < $jumlahBaru) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Stok barang baru tidak cukup untuk jumlah penjualan yang dipilih.',
                    ]);
                }
                $produkLama->stok = FinancialAmount::quantityFromThousandths($stokLama);
                $produkBaru->stok = FinancialAmount::quantityFromThousandths($stokTersediaBaru - $jumlahBaru);
            }

            if ($lockedPenjualan->produk_id === (int) $data['produk_id'] && $stokBaru < 0) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Stok tidak cukup untuk jumlah penjualan yang baru.',
                ]);
            }

            if ($lockedPenjualan->produk_id === (int) $data['produk_id']) {
                $produkLama->stok = FinancialAmount::quantityFromThousandths($stokBaru);
            }

            $hargaBeli = $lockedPenjualan->produk_id === (int) $data['produk_id']
                ? (string) $lockedPenjualan->harga_beli_satuan
                : (string) $produkBaru->harga_beli;
            $lockedPenjualan->update([
                'produk_id' => $produkBaru->id,
                'tanggal' => $data['tanggal'],
                'satuan' => trim($data['satuan']),
                'jumlah' => $data['jumlah'],
                'harga_beli_satuan' => $hargaBeli,
                'harga_jual' => $data['harga_jual'],
                'total_harga_beli' => FinancialAmount::multiply((string) $data['jumlah'], $hargaBeli),
                'total_harga_jual' => FinancialAmount::multiply((string) $data['jumlah'], (string) $data['harga_jual']),
                'laba' => FinancialAmount::profit((string) $data['jumlah'], (string) $data['harga_jual'], $hargaBeli),
            ]);

            foreach ($produks as $produk) {
                $produk->harga_jual = (string) (Penjualan::query()
                    ->where('produk_id', $produk->id)
                    ->orderByDesc('id')
                    ->value('harga_jual') ?? '0');
                $produk->save();
            }
        });

        return redirect()->route('penjualan.create.list')->with('success', 'Transaksi penjualan berhasil diperbarui dan stok disesuaikan.');
    }

    public function destroy(Penjualan $penjualan): RedirectResponse
    {
        DB::transaction(function () use ($penjualan): void {
            $lockedPenjualan = Penjualan::query()->whereKey($penjualan->id)->lockForUpdate()->firstOrFail();
            $produk = Produk::query()->whereKey($lockedPenjualan->produk_id)->lockForUpdate()->first();

            if ($produk) {
                $produk->stok = FinancialAmount::quantityFromThousandths(
                    FinancialAmount::quantityInThousandths((string) $produk->stok)
                    + FinancialAmount::quantityInThousandths((string) $lockedPenjualan->jumlah)
                );
            }

            $lockedPenjualan->delete();

            if ($produk) {
                $produk->harga_jual = (string) (Penjualan::query()
                    ->where('produk_id', $produk->id)
                    ->orderByDesc('id')
                    ->value('harga_jual') ?? '0');
                $produk->save();
            }
        });

        return redirect()->route('penjualan.history')->with('success', 'Transaksi penjualan berhasil dihapus dan stok disesuaikan.');
    }

    public function createList(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pemilik' => ['nullable', 'string', 'exists:pemiliks,kode'],
        ]);
        $pemiliks = Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get();
        $lastKode = session('last_pemilik_kode');
        $query = Penjualan::query()->with('produk.pemilik');

        if (! empty($filters['tanggal_mulai'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_mulai']);
        }
        if (! empty($filters['tanggal_selesai'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_selesai']);
        }
        if (! empty($filters['pemilik'])) {
            $query->whereHas('produk.pemilik', fn ($ownerQuery) => $ownerQuery->where('kode', $filters['pemilik']));
        }

        return view($request->routeIs('penjualan.history')
            ? 'keuangan.transaksi.history'
            : 'keuangan.transaksi.stock-list', [
            'jenisTransaksi' => 'Penjualan',
            'nomorBerikutnya' => Penjualan::query()->count() + 1,
            'pemiliks' => $pemiliks,
            'lastPemilikKode' => $pemiliks->contains('kode', $lastKode) ? $lastKode : null,
            'produks' => Produk::query()->with('pemilik')->orderBy('nama')->get(),
            'produksTersedia' => Produk::query()
                ->where('stok', '>', 0)
                ->with('pemilik')
                ->orderBy('nama')
                ->orderBy('satuan')
                ->get(['id', 'pemilik_id', 'nama', 'satuan', 'stok']),
            'transaksiList' => $query->latest('tanggal')->latest('id')->get()->map(fn (Penjualan $p) => (object) [
                'id' => $p->id,
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_jual,
                'jumlah' => $p->jumlah,
                'total' => $p->total_harga_jual,
                'ownerKode' => $p->produk->pemilik->kode ?? '',
                'ownerNama' => $p->produk->pemilik->nama ?? 'Belum ditentukan',
            ]),
            'pemilikTerpilih' => $filters['pemilik'] ?? '',
            'tanggalMulai' => $filters['tanggal_mulai'] ?? '',
            'tanggalSelesai' => $filters['tanggal_selesai'] ?? '',
            'pemilikTerpilihNama' => ! empty($filters['pemilik'])
                ? Pemilik::query()->where('kode', $filters['pemilik'])->value('nama')
                : null,
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

        return redirect()->route('penjualan.create.list')->with('success', 'Penjualan dicatat dan stok barang berkurang.');
    }
}

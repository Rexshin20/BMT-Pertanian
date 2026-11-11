<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProdukController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('penjualan.create.list');
    }

    public function create(): View
    {
        $pemiliks = Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get();
        $lastKode = session('last_pemilik_kode');

        return view('keuangan.produk.form', [
            'produk' => null,
            'pemiliks' => $pemiliks,
            'lastPemilikKode' => $pemiliks->contains('kode', $lastKode) ? $lastKode : null,
        ]);
    }

    public function edit(Produk $produk): View
    {
        return view('keuangan.produk.form', [
            'produk' => $produk->load('pemilik'),
            'pemiliks' => Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pemilik_kode' => ['required', 'string', 'regex:/^\d{3,10}$/', 'exists:pemiliks,kode'],
            'satuan' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:150'],
            'harga_jual' => ['nullable', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
        ]);

        $pemilik = Pemilik::query()->where('kode', $data['pemilik_kode'])->firstOrFail();
        $this->ensureUniqueVariant($pemilik->id, $data['nama'], $data['satuan']);

        session(['last_pemilik_kode' => $data['pemilik_kode']]);

        Produk::query()->create([
            'pemilik_id' => $pemilik->id,
            'nama' => trim($data['nama']),
            'satuan' => trim($data['satuan']),
            'harga_jual' => $data['harga_jual'] ?? '0',
        ]);

        return redirect()->route('penjualan.create.list')
            ->with('success', 'Barang berhasil ditambahkan. Catat pembelian untuk menambah stok.');
    }

    public function update(Request $request, Produk $produk): RedirectResponse
    {
        $data = $request->validate([
            'pemilik_kode' => ['required', 'string', 'regex:/^\d{3,10}$/', 'exists:pemiliks,kode'],
            'satuan' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:150'],
            'harga_jual' => ['nullable', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
        ]);

        $pemilik = Pemilik::query()->where('kode', $data['pemilik_kode'])->firstOrFail();
        $this->ensureUniqueVariant($pemilik->id, $data['nama'], $data['satuan'], $produk->id);

        session(['last_pemilik_kode' => $data['pemilik_kode']]);

        $produk->update([
            'pemilik_id' => $pemilik->id,
            'nama' => trim($data['nama']),
            'satuan' => trim($data['satuan']),
            'harga_jual' => $data['harga_jual'] ?? '0',
        ]);

        return redirect()->route('penjualan.create.list')->with('success', 'Data barang berhasil diperbarui.');
    }

    public function destroy(Produk $produk): RedirectResponse
    {
        DB::transaction(function () use ($produk): void {
            Penjualan::query()->where('produk_id', $produk->id)->delete();
            Pembelian::query()->where('produk_id', $produk->id)->delete();
            $produk->delete();
        });

        return redirect()->route('penjualan.create.list')
            ->with('success', 'Barang dan seluruh transaksi terkait berhasil dihapus.');
    }

    private function ensureUniqueVariant(int $pemilikId, string $nama, string $satuan, ?int $ignoreId = null): void
    {
        $exists = Produk::query()
            ->where('pemilik_id', $pemilikId)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim($nama))])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(
                fn (Produk $produk) => Produk::normalizeSatuan($produk->satuan) === Produk::normalizeSatuan($satuan)
            );

        if ($exists) {
            throw ValidationException::withMessages([
                'nama' => 'Barang dengan nama dan satuan yang sama sudah ada untuk pemilik ini.',
            ]);
        }
    }
}

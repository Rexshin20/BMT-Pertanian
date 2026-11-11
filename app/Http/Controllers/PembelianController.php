<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Produk;
use App\Support\FinancialAmount;
use App\Support\PembelianImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PembelianController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pemilik' => ['nullable', 'string', 'exists:pemiliks,kode'],
        ]);
        $query = Pembelian::query()->with('produk.pemilik');

        if (! empty($filters['pemilik'])) {
            $query->whereHas('produk.pemilik', fn ($ownerQuery) => $ownerQuery->where('kode', $filters['pemilik']));
        }

        $pembelians = $query
            ->latest('tanggal')
            ->latest('id')
            ->get()
            ->map(fn (Pembelian $p) => (object) [
                'id' => $p->id,
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_beli,
                'jumlah' => $p->jumlah,
                'ownerKode' => $p->produk->pemilik->kode ?? '',
            ]);

        if (empty($filters['pemilik'])) {
            $pembelians = $pembelians
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

        return view('keuangan.pembelian.master-data', [
            'pembelians' => $pembelians,
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

    public function edit(Pembelian $pembelian): View
    {
        return view('keuangan.transaksi.edit', [
            'jenisTransaksi' => 'Pembelian',
            'transaksi' => $pembelian->load('produk.pemilik'),
            'hargaField' => 'harga_beli',
            'hargaLabel' => 'Harga beli per unit',
            'updateRoute' => 'pembelian.update',
            'backRoute' => 'pembelian.create.list',
        ]);
    }

    public function update(Request $request, Pembelian $pembelian): RedirectResponse
    {
        $data = $request->validate([
            'produk_id' => ['required', 'integer', 'exists:produks,id'],
            'satuan' => ['required', 'string', 'max:30'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jumlah' => ['required', 'numeric', 'regex:/^\d{1,6}(?:\.\d{1,3})?$/', 'gt:0'],
            'harga_beli' => ['required', 'numeric', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'gt:0'],
        ]);

        DB::transaction(function () use ($data, $pembelian): void {
            $lockedPembelian = Pembelian::query()->whereKey($pembelian->id)->lockForUpdate()->firstOrFail();
            $produkIds = collect([$lockedPembelian->produk_id, (int) $data['produk_id']])->unique()->sort()->values();
            $produks = Produk::query()->whereIn('id', $produkIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $produkLama = $produks->get($lockedPembelian->produk_id);
            $produkBaru = $produks->get((int) $data['produk_id']);
            if (! $produkLama || ! $produkBaru) {
                throw ValidationException::withMessages([
                    'produk_id' => 'Barang yang dipilih tidak ditemukan.',
                ]);
            }
            $jumlahLama = FinancialAmount::quantityInThousandths((string) $lockedPembelian->jumlah);
            $jumlahBaru = FinancialAmount::quantityInThousandths((string) $data['jumlah']);

            if ($lockedPembelian->produk_id === (int) $data['produk_id']) {
                $stokBaru = FinancialAmount::quantityInThousandths((string) $produkLama->stok) + $jumlahBaru - $jumlahLama;
                if ($stokBaru < 0) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Jumlah pembelian tidak bisa dikurangi karena stok barang akan menjadi negatif.',
                    ]);
                }
                $produkLama->stok = FinancialAmount::quantityFromThousandths($stokBaru);
            } else {
                $stokLama = FinancialAmount::quantityInThousandths((string) $produkLama->stok) - $jumlahLama;
                if ($stokLama < 0) {
                    throw ValidationException::withMessages([
                        'produk_id' => 'Stok barang lama sudah terpakai, jadi transaksi ini tidak bisa dipindahkan.',
                    ]);
                }
                $produkLama->stok = FinancialAmount::quantityFromThousandths($stokLama);
                $produkBaru->stok = FinancialAmount::quantityFromThousandths(
                    FinancialAmount::quantityInThousandths((string) $produkBaru->stok) + $jumlahBaru
                );
            }

            $lockedPembelian->update([
                'produk_id' => $produkBaru->id,
                'tanggal' => $data['tanggal'],
                'satuan' => trim($data['satuan']),
                'jumlah' => $data['jumlah'],
                'harga_beli' => $data['harga_beli'],
                'total_harga' => FinancialAmount::multiply((string) $data['jumlah'], (string) $data['harga_beli']),
            ]);

            foreach ($produks as $produk) {
                $produk->harga_beli = (string) (Pembelian::query()
                    ->where('produk_id', $produk->id)
                    ->orderByDesc('id')
                    ->value('harga_beli') ?? '0');
                $produk->save();
            }
        });

        return redirect()->route('pembelian.create.list')->with('success', 'Transaksi pembelian berhasil diperbarui dan stok disesuaikan.');
    }

    public function destroy(Pembelian $pembelian): RedirectResponse
    {
        DB::transaction(function () use ($pembelian): void {
            $lockedPembelian = Pembelian::query()->whereKey($pembelian->id)->lockForUpdate()->firstOrFail();
            $produk = Produk::query()->whereKey($lockedPembelian->produk_id)->lockForUpdate()->first();

            if ($produk) {
                $stokBaru = FinancialAmount::quantityInThousandths((string) $produk->stok)
                    - FinancialAmount::quantityInThousandths((string) $lockedPembelian->jumlah);

                if ($stokBaru < 0) {
                    throw ValidationException::withMessages([
                        'pembelian' => 'Transaksi tidak bisa dihapus karena stok barang sudah terpakai.',
                    ]);
                }

                $produk->stok = FinancialAmount::quantityFromThousandths($stokBaru);
            }

            $lockedPembelian->delete();

            if ($produk) {
                $produk->harga_beli = (string) (Pembelian::query()
                    ->where('produk_id', $produk->id)
                    ->orderByDesc('id')
                    ->value('harga_beli') ?? '0');
                $produk->save();
            }
        });

        return redirect()->route('pembelian.history')->with('success', 'Transaksi pembelian berhasil dihapus dan stok disesuaikan.');
    }

    public function createList(Request $request): View    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'pemilik' => ['nullable', 'string', 'exists:pemiliks,kode'],
        ]);
        $pemiliks = Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get();
        $lastKode = session('last_pemilik_kode');
        $query = Pembelian::query()->with('produk.pemilik');

        if (! empty($filters['tanggal_mulai'])) {
            $query->whereDate('tanggal', '>=', $filters['tanggal_mulai']);
        }
        if (! empty($filters['tanggal_selesai'])) {
            $query->whereDate('tanggal', '<=', $filters['tanggal_selesai']);
        }
        if (! empty($filters['pemilik'])) {
            $query->whereHas('produk.pemilik', fn ($ownerQuery) => $ownerQuery->where('kode', $filters['pemilik']));
        }

        return view($request->routeIs('pembelian.history')
            ? 'keuangan.transaksi.history'
            : 'keuangan.transaksi.stock-list', [
            'jenisTransaksi' => 'Pembelian',
            'nomorBerikutnya' => Pembelian::query()->count() + 1,
            'pemiliks' => $pemiliks,
            'lastPemilikKode' => $pemiliks->contains('kode', $lastKode) ? $lastKode : null,
            'produks' => Produk::query()->with('pemilik')->orderBy('nama')->get(),
            'transaksiList' => $query->latest('tanggal')->latest('id')->get()->map(fn (Pembelian $p) => (object) [
                'id' => $p->id,
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_beli,
                'jumlah' => $p->jumlah,
                'total' => $p->total_harga,
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
            'nama_barang' => ['required', 'string', 'max:150'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'satuan' => ['required', 'string', 'max:30'],
            'jumlah' => ['required', 'numeric', 'regex:/^\d{1,6}(?:\.\d{1,3})?$/', 'gt:0'],
            'harga_beli' => ['required', 'numeric', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', 'gt:0'],
        ]);

        DB::transaction(fn () => $this->recordPurchase($data));

        session(['last_pemilik_kode' => $data['pemilik_kode']]);

        return redirect()->route('pembelian.create.list')->with('success', 'Pembelian dicatat dan stok barang bertambah.');
    }

    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        PembelianImport::writeTemplate($path);

        return response()->download($path, 'template-pembelian.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'mimes:xlsx,xls,csv,txt,pdf', 'max:5120'],
        ], [], ['berkas' => 'File']);

        try {
            $berkas = $request->file('berkas');
            $rows = PembelianImport::parse($berkas->getRealPath(), null, $berkas->getClientOriginalName());
        } catch (RuntimeException $e) {
            return redirect()->route('pembelian.create')->withErrors(['berkas' => $e->getMessage()]);
        }

        $token = (string) Str::uuid();
        session(['pembelian_import' => ['token' => $token, 'rows' => $rows]]);

        return view('keuangan.pembelian.import-preview', [
            'rows' => $rows,
            'token' => $token,
            'validCount' => collect($rows)->filter(fn ($row) => $row['errors'] === [])->count(),
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $pending = session('pembelian_import');

        if (! $pending || ! hash_equals($pending['token'], (string) $request->input('token'))) {
            return redirect()->route('pembelian.create')
                ->withErrors(['berkas' => 'Sesi impor kedaluwarsa. Silakan upload ulang file.']);
        }

        $valid = collect($pending['rows'])->filter(fn ($row) => $row['errors'] === []);
        $skipped = count($pending['rows']) - $valid->count();

        DB::transaction(function () use ($valid): void {
            foreach ($valid as $row) {
                $this->recordPurchase($row['data']);
            }
        });

        session()->forget('pembelian_import');

        $message = $valid->count().' baris pembelian berhasil diimpor dan stok bertambah.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' baris bermasalah dilewati.';
        }

        return redirect()->route('pembelian.index')->with('success', $message);
    }

    private function recordPurchase(array $data): void
    {
        $namaBarang = trim($data['nama_barang']);
        $satuan = trim($data['satuan']);
        $pemilik = Pemilik::query()
            ->where('kode', $data['pemilik_kode'])
            ->lockForUpdate()
            ->firstOrFail();
        $produk = Produk::findVariant($pemilik->id, $namaBarang, $satuan);

        if (! $produk) {
            $produk = new Produk([
                'nama' => $namaBarang,
                'pemilik_id' => $pemilik->id,
            ]);
            if ($satuan !== '') {
                $produk->satuan = $satuan;
            }
            $produk->save();
        }

        $jumlah = (string) $data['jumlah'];
        $hargaBeli = (string) $data['harga_beli'];

        Pembelian::query()->create([
            'produk_id' => $produk->id,
            'tanggal' => $data['tanggal'],
            'satuan' => $satuan,
            'jumlah' => $jumlah,
            'harga_beli' => $hargaBeli,
            'total_harga' => FinancialAmount::multiply($jumlah, $hargaBeli),
        ]);

        $produk->increment('stok', $jumlah);
        $produk->harga_beli = $hargaBeli;
        $produk->save();
    }
}

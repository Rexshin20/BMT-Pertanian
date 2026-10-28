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
                'tanggal' => $p->tanggal,
                'nama_barang' => trim($p->produk->nama),
                'satuan' => trim($p->satuan ?: $p->produk->satuan),
                'harga' => $p->harga_beli,
                'jumlah' => $p->jumlah,
                'ownerKode' => $p->produk->pemilik->kode ?? '',
            ]);

        // "Semua nama": transaksi yang tanggal, barang, satuan, dan harganya sama
        // digabung jadi satu baris (jumlah dijumlah) biar tabel tidak boros.
        if (empty($filters['pemilik'])) {
            $pembelians = $pembelians
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

    public function create(): View
    {
        $pemiliks = Pemilik::query()->orderByRaw('CAST(kode AS DECIMAL(20, 0))')->get();
        $lastKode = session('last_pemilik_kode');

        return view('keuangan.pembelian.create', [
            'nomorBerikutnya' => Pembelian::query()->count() + 1,
            'pemiliks' => $pemiliks,
            'lastPemilikKode' => $pemiliks->contains('kode', $lastKode) ? $lastKode : null,
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

        return redirect()->route('pembelian.create')->with('success', 'Pembelian dicatat dan stok barang bertambah.');
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
        $produk = Produk::query()
            ->where('pemilik_id', $pemilik->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaBarang)])
            ->lockForUpdate()
            ->first();

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

<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Pemilik;
use App\Models\Penjualan;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);

        $today = Carbon::today();
        $tanggalMulai = Carbon::parse(($filters['tanggal_mulai'] ?? null) ?: $today->copy()->startOfMonth()->toDateString())->startOfDay();
        $tanggalSelesai = Carbon::parse(($filters['tanggal_selesai'] ?? null) ?: $today->toDateString())->startOfDay();
        $dateFrom = $tanggalMulai->toDateString();
        $dateTo = $tanggalSelesai->toDateString();

        $periodeLabel = $tanggalMulai->copy()->locale('id')->translatedFormat('d M Y')
            .' – '.$tanggalSelesai->copy()->locale('id')->translatedFormat('d M Y');

        $salesByDate = Penjualan::query()
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->selectRaw('tanggal, SUM(total_harga_jual) AS total_sales, SUM(total_harga_beli) AS total_cost, SUM(laba) AS total_profit')
            ->groupBy('tanggal')
            ->get()
            ->keyBy(fn (Penjualan $transaction) => $transaction->tanggal->toDateString());
        $dailyPurchases = Pembelian::query()
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->selectRaw('tanggal, SUM(total_harga) AS total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        // Rentang panjang dikelompokkan per bulan agar grafik tetap terbaca.
        $groupByMonth = $tanggalMulai->diffInDays($tanggalSelesai) > 62;
        $chartDates = collect();
        $chartSales = [];
        $chartPurchases = [];
        $chartProfit = [];

        if ($groupByMonth) {
            for ($cursor = $tanggalMulai->copy()->startOfMonth(); $cursor->lte($tanggalSelesai); $cursor->addMonth()) {
                $monthKey = $cursor->format('Y-m');
                $salesInMonth = $salesByDate->filter(fn ($row, $date) => str_starts_with((string) $date, $monthKey));
                $purchasesInMonth = $dailyPurchases->filter(fn ($total, $date) => str_starts_with((string) $date, $monthKey));

                $chartDates->push($cursor->copy()->locale('id')->translatedFormat('M Y'));
                $chartSales[] = (float) $salesInMonth->sum('total_sales');
                $chartPurchases[] = (float) $purchasesInMonth->sum();
                $chartProfit[] = (float) $salesInMonth->sum('total_profit');
            }
        } else {
            for ($date = $tanggalMulai->copy(); $date->lte($tanggalSelesai); $date->addDay()) {
                $key = $date->toDateString();
                $chartDates->push($date->format('d/m'));
                $chartSales[] = (float) ($salesByDate[$key]->total_sales ?? 0);
                $chartPurchases[] = (float) ($dailyPurchases[$key] ?? 0);
                $chartProfit[] = (float) ($salesByDate[$key]->total_profit ?? 0);
            }
        }

        $totalSales = (float) $salesByDate->sum('total_sales');
        $totalCost = (float) $salesByDate->sum('total_cost');
        $totalPurchases = (float) $dailyPurchases->sum();
        $totalProfit = (float) $salesByDate->sum('total_profit');

        $salesByOwner = Penjualan::query()
            ->join('produks', 'penjuwalans.produk_id', '=', 'produks.id')
            ->whereBetween('penjuwalans.tanggal', [$dateFrom, $dateTo])
            ->selectRaw('produks.pemilik_id, SUM(penjuwalans.total_harga_jual) AS total_sales')
            ->groupBy('produks.pemilik_id')
            ->pluck('total_sales', 'pemilik_id');
        $purchasesByOwner = Pembelian::query()
            ->join('produks', 'pembelians.produk_id', '=', 'produks.id')
            ->whereBetween('pembelians.tanggal', [$dateFrom, $dateTo])
            ->selectRaw('produks.pemilik_id, SUM(pembelians.total_harga) AS total_purchases')
            ->groupBy('produks.pemilik_id')
            ->pluck('total_purchases', 'pemilik_id');

        $unassignedSales = (float) Penjualan::query()
            ->join('produks', 'penjuwalans.produk_id', '=', 'produks.id')
            ->whereNull('produks.pemilik_id')
            ->whereBetween('penjuwalans.tanggal', [$dateFrom, $dateTo])
            ->sum('penjuwalans.total_harga_jual');
        $unassignedPurchases = (float) Pembelian::query()
            ->join('produks', 'pembelians.produk_id', '=', 'produks.id')
            ->whereNull('produks.pemilik_id')
            ->whereBetween('pembelians.tanggal', [$dateFrom, $dateTo])
            ->sum('pembelians.total_harga');

        $storeRows = Pemilik::query()
            ->orderByRaw('CAST(kode AS DECIMAL(20, 0))')
            ->get()
            ->map(fn (Pemilik $pemilik) => [
                'id' => $pemilik->id_tampilan,
                'nama' => $pemilik->nama,
                'sales' => (float) ($salesByOwner[$pemilik->id] ?? 0),
                'purchases' => (float) ($purchasesByOwner[$pemilik->id] ?? 0),
            ]);

        if ($unassignedSales > 0 || $unassignedPurchases > 0) {
            $storeRows->push([
                'id' => '-',
                'nama' => 'Tanpa pemilik',
                'sales' => $unassignedSales,
                'purchases' => $unassignedPurchases,
            ]);
        }

        $storeSummaries = $storeRows
            ->sortByDesc('sales')
            ->values()
            ->all();

        $terjualSetelah = Penjualan::query()
            ->whereDate('tanggal', '>', $dateTo)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, COALESCE(SUM(jumlah), 0) AS total_jumlah')
            ->pluck('total_jumlah', 'produk_id');
        $dibeliSetelah = Pembelian::query()
            ->whereDate('tanggal', '>', $dateTo)
            ->groupBy('produk_id')
            ->selectRaw('produk_id, COALESCE(SUM(jumlah), 0) AS total_jumlah')
            ->pluck('total_jumlah', 'produk_id');

        $stokAkhirs = Produk::query()
            ->with('pemilik')
            ->get()
            ->map(function (Produk $produk) use ($terjualSetelah, $dibeliSetelah): array {
                // Stok sekarang disesuaikan dengan transaksi setelah tanggal akhir:
                // penjualan setelah tanggal ditambahkan, pembelian setelah tanggal
                // dikurangkan, supaya angkanya sesuai posisi stok pada tanggal itu.
                $stok = (float) $produk->stok
                    + (float) ($terjualSetelah[$produk->id] ?? 0)
                    - (float) ($dibeliSetelah[$produk->id] ?? 0);
                $hargaBeli = (float) ($produk->harga_beli ?? 0);

                return [
                    'nama' => trim($produk->nama),
                    'toko' => $produk->pemilik->nama ?? 'Tanpa pemilik',
                    'satuan' => trim((string) $produk->satuan),
                    'stok' => $stok,
                    'harga_beli' => $hargaBeli,
                    'nilai' => $stok > 0 ? $stok * $hargaBeli : 0.0,
                ];
            })
            ->filter(fn (array $item) => $item['stok'] > 0)
            ->sortByDesc('nilai')
            ->values();

        $totalNilaiStok = (float) $stokAkhirs->sum('nilai');
        $totalItemStok = $stokAkhirs->count();
        $stokPerTanggal = $tanggalSelesai->copy()->locale('id')->translatedFormat('d M Y');

        $earliestTransaction = collect([
            Penjualan::query()->min('tanggal'),
            Pembelian::query()->min('tanggal'),
        ])->filter()->sort()->first() ?? $today->toDateString();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $yearStart = $today->copy()->startOfYear()->toDateString();

        $filterPresets = [
            [
                'label' => 'Hari ini',
                'url' => route('dashboard', ['tanggal_mulai' => $today->toDateString(), 'tanggal_selesai' => $today->toDateString()]),
                'active' => $dateFrom === $today->toDateString() && $dateTo === $today->toDateString(),
            ],
            [
                'label' => 'Bulan ini',
                'url' => route('dashboard'),
                'active' => $dateFrom === $monthStart && $dateTo === $today->toDateString() && $dateFrom !== $today->toDateString(),
            ],
            [
                'label' => 'Tahun ini',
                'url' => route('dashboard', ['tanggal_mulai' => $yearStart, 'tanggal_selesai' => $today->toDateString()]),
                'active' => $dateFrom === $yearStart && $dateTo === $today->toDateString(),
            ],
            [
                'label' => 'Semua data',
                'url' => route('dashboard', ['tanggal_mulai' => $earliestTransaction, 'tanggal_selesai' => $today->toDateString()]),
                'active' => $dateFrom === $earliestTransaction && $dateTo === $today->toDateString(),
            ],
        ];

        $sales = Penjualan::query()
            ->with('produk')
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->latest('tanggal')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Penjualan $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->tanggal,
                'created_at' => $transaction->created_at,
                'name' => $transaction->produk->nama,
                'unit' => $transaction->satuan ?: $transaction->produk->satuan,
                'quantity' => (float) $transaction->jumlah,
                'type' => 'Penjualan',
                'amount' => (float) $transaction->total_harga_jual,
            ]);
        $purchases = Pembelian::query()
            ->with('produk')
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->latest('tanggal')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Pembelian $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->tanggal,
                'created_at' => $transaction->created_at,
                'name' => $transaction->produk->nama,
                'unit' => $transaction->satuan ?: $transaction->produk->satuan,
                'quantity' => (float) $transaction->jumlah,
                'type' => 'Pembelian',
                'amount' => (float) $transaction->total_harga,
            ]);

        return view('pages.index', [
            'dashboardData' => [
                'labels' => $chartDates->all(),
                'sales' => $chartSales,
                'purchases' => $chartPurchases,
                'profit' => $chartProfit,
                'year' => $today->year,
                'yearSales' => $totalSales,
                'yearCost' => $totalCost,
                'yearProfit' => $totalProfit,
                'monthProfit' => array_sum($chartProfit),
                'monthName' => $periodeLabel,
                'periodeLabel' => $periodeLabel,
                'chartTitle' => $groupByMonth ? 'Penjualan & Pembelian per Bulan' : 'Penjualan & Pembelian per Hari',
            ],
            'periodeLabel' => $periodeLabel,
            'tanggalMulai' => $dateFrom,
            'tanggalSelesai' => $dateTo,
            'filterPresets' => $filterPresets,
            'totalSales' => $totalSales,
            'totalPurchases' => $totalPurchases,
            'storeSummaries' => $storeSummaries,
            'stokAkhirs' => $stokAkhirs,
            'totalNilaiStok' => $totalNilaiStok,
            'totalItemStok' => $totalItemStok,
            'stokPerTanggal' => $stokPerTanggal,
            'recentTransactions' => $sales
                ->concat($purchases)
                ->sortByDesc(fn (array $transaction) => $transaction['created_at']?->timestamp ?? 0)
                ->take(8)
                ->values(),
        ]);
    }
}

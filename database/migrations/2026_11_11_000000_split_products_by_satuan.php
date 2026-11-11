<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->unique(['pemilik_id', 'nama', 'satuan'], 'produks_pemilik_id_nama_satuan_unique');
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->dropUnique('produks_pemilik_id_nama_unique');
        });

        DB::transaction(function (): void {
            foreach (DB::table('produks')->orderBy('id')->get() as $produk) {
                $this->splitProduct($produk);
            }
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('produks')
            ->select('pemilik_id', 'nama')
            ->groupBy('pemilik_id', 'nama')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Tidak bisa rollback selama masih ada beberapa satuan untuk barang yang sama.');
        }

        Schema::table('produks', function (Blueprint $table) {
            $table->unique(['pemilik_id', 'nama'], 'produks_pemilik_id_nama_unique');
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->dropUnique('produks_pemilik_id_nama_satuan_unique');
        });
    }

    private function splitProduct(object $produk): void
    {
        $purchases = DB::table('pembelians')
            ->where('produk_id', $produk->id)
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($purchases as $purchase) {
            $satuan = trim((string) ($purchase->satuan ?? ''));

            if ($satuan === '') {
                $satuan = trim((string) $produk->satuan);
            }

            $key = $this->normalizeSatuan($satuan);

            if ($key === '') {
                $key = '_';
            }

            $groups[$key] ??= [
                'satuan' => $satuan,
                'ids' => [],
                'harga_beli' => (string) $purchase->harga_beli,
            ];

            $groups[$key]['ids'][] = $purchase->id;
            $groups[$key]['harga_beli'] = (string) $purchase->harga_beli;
        }

        if (count($groups) <= 1) {
            return;
        }

        $keepKey = $this->normalizeSatuan($produk->satuan);

        if (! array_key_exists($keepKey, $groups)) {
            $keepKey = array_key_first($groups);
        }

        $keep = $groups[$keepKey];
        unset($groups[$keepKey]);

        $now = now();

        DB::table('produks')->where('id', $produk->id)->update([
            'satuan' => $keep['satuan'],
            'updated_at' => $now,
        ]);

        $affected = [$produk->id];

        foreach ($groups as $key => $group) {
            $newId = DB::table('produks')->insertGetId([
                'pemilik_id' => $produk->pemilik_id,
                'nama' => $produk->nama,
                'satuan' => $group['satuan'],
                'stok' => 0,
                'harga_beli' => $group['harga_beli'],
                'harga_jual' => $produk->harga_jual,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pembelians')->whereIn('id', $group['ids'])->update([
                'produk_id' => $newId,
                'updated_at' => $now,
            ]);

            $saleIds = DB::table('penjuwalans')
                ->where('produk_id', $produk->id)
                ->get(['id', 'satuan'])
                ->filter(function (object $sale) use ($key): bool {
                    $satuan = trim((string) ($sale->satuan ?? ''));

                    return $satuan !== '' && $this->normalizeSatuan($satuan) === $key;
                })
                ->pluck('id');

            if ($saleIds->isNotEmpty()) {
                DB::table('penjuwalans')->whereIn('id', $saleIds)->update([
                    'produk_id' => $newId,
                    'updated_at' => $now,
                ]);
            }

            $affected[] = $newId;
        }

        foreach ($affected as $produkId) {
            $totalBeli = (float) DB::table('pembelians')->where('produk_id', $produkId)->sum('jumlah');
            $totalJual = (float) DB::table('penjuwalans')->where('produk_id', $produkId)->sum('jumlah');
            $hargaBeli = DB::table('pembelians')
                ->where('produk_id', $produkId)
                ->orderByDesc('id')
                ->value('harga_beli');

            $update = [
                'stok' => number_format($totalBeli - $totalJual, 3, '.', ''),
                'updated_at' => $now,
            ];

            if ($hargaBeli !== null) {
                $update['harga_beli'] = $hargaBeli;
            }

            DB::table('produks')->where('id', $produkId)->update($update);
        }
    }

    private function normalizeSatuan(?string $satuan): string
    {
        $normalized = mb_strtolower(trim((string) $satuan));
        $normalized = preg_replace('/^per\s+/u', '', $normalized) ?? $normalized;

        return preg_replace('/\s+/u', '', $normalized) ?? $normalized;
    }
};

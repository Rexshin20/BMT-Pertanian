<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'pemilik_id',
        'satuan',
        'harga_jual',
    ];

    protected $casts = [
        'stok' => 'decimal:3',
        'harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
    ];

    public static function normalizeSatuan(?string $satuan): string
    {
        $normalized = mb_strtolower(trim((string) $satuan));
        $normalized = preg_replace('/^per\s+/u', '', $normalized) ?? $normalized;

        return preg_replace('/\s+/u', '', $normalized) ?? $normalized;
    }

    public static function findVariant(int $pemilikId, string $nama, string $satuan): ?self
    {
        $produks = self::query()
            ->where('pemilik_id', $pemilikId)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim($nama))])
            ->lockForUpdate()
            ->get();

        $normalized = self::normalizeSatuan($satuan);

        if ($normalized === '') {
            return $produks->first();
        }

        return $produks->first(
            fn (self $produk) => self::normalizeSatuan($produk->satuan) === $normalized
        );
    }

    public function pembelians(): HasMany
    {
        return $this->hasMany(Pembelian::class);
    }

    public function penjuwalans(): HasMany
    {
        return $this->hasMany(Penjualan::class);
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(Pemilik::class);
    }
}

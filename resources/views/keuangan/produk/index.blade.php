@extends('layouts.modernize')

@section('title', 'Master Barang')
@section('page-title', 'Master Barang')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Daftar Barang</h5>
        <span class="text-muted fs-3">Stok terisi otomatis dari transaksi pembelian dan penjualan. Penghapusan juga menghapus transaksi terkait.</span>
      </div>
      <a class="btn btn-primary" href="{{ route('produk.create') }}">Tambah Barang</a>
    </div>
    <div class="table-responsive">
      <table id="produk-table" class="table table-hover align-middle text-nowrap mb-0">
        <thead>
          <tr>
            <th class="text-center" style="width: 64px;">ID</th>
            <th style="min-width: 160px;">Nama Pemilik</th>
            <th style="min-width: 240px;">Nama Barang</th>
            <th class="text-center" style="width: 110px;">Satuan</th>
            <th class="text-end" style="width: 110px;">Stok</th>
            <th class="text-end" style="width: 120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($produks as $produk)
            <tr>
              <td class="text-center fw-semibold">{{ $produk->pemilik?->id_tampilan ?? '-' }}</td>
              <td>{{ $produk->pemilik?->nama ?? 'Belum ditentukan' }}</td>
              <td class="fw-semibold">{{ $produk->nama }}</td>
              <td class="text-center">{{ $produk->satuan }}</td>
              <td class="text-end fw-semibold {{ (float) $produk->stok < 0 ? 'text-danger' : '' }}" data-order="{{ (float) $produk->stok }}">
                {{ rtrim(rtrim(number_format((float) $produk->stok, 3, ',', '.'), '0'), ',') }}
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-primary" href="{{ route('produk.edit', $produk) }}" aria-label="Edit {{ $produk->nama }}" title="Edit {{ $produk->nama }}">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                  </a>
                  <form method="POST" action="{{ route('produk.destroy', $produk) }}" onsubmit="return confirm('Hapus barang {{ $produk->nama }}? Semua transaksi pembelian dan penjualan barang ini juga akan dihapus permanen.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger" type="submit" aria-label="Hapus {{ $produk->nama }}" title="Hapus {{ $produk->nama }}">
                      <i class="ti ti-trash" aria-hidden="true"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@include('keuangan.partials.data-table', [
    'tableId' => 'produk-table',
    'title' => 'Master Barang',
    'emptyMessage' => 'Belum ada barang. Klik Tambah Barang untuk mulai menambahkan.',
    'withExports' => false,
    'renumberFirstColumn' => false,
    'nonOrderableColumns' => [5],
])

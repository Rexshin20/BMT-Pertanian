@extends('layouts.modernize')

@section('title', 'Riwayat '.$jenisTransaksi)
@section('page-title', 'Riwayat '.$jenisTransaksi)

@section('content')
@php($tableId = $jenisTransaksi === 'Penjualan' ? 'penjualan-history-table' : 'pembelian-history-table')
@php($editRoute = $jenisTransaksi === 'Penjualan' ? 'penjualan.edit' : 'pembelian.edit')
@php($deleteRoute = $jenisTransaksi === 'Penjualan' ? 'penjualan.destroy' : 'pembelian.destroy')
@php($createRoute = $jenisTransaksi === 'Penjualan' ? 'penjualan.create.list' : 'pembelian.create.list')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Riwayat {{ $jenisTransaksi }}</h5>
        <span class="text-muted fs-3">Gunakan ikon pensil untuk mengubah dan ikon sampah untuk menghapus transaksi; stok otomatis disesuaikan.</span>
      </div>
      <a class="btn btn-primary btn-sm" href="{{ route($createRoute) }}">Kembali ke Create {{ $jenisTransaksi }}</a>
    </div>
    @include('keuangan.partials.date-filter', ['tableId' => $tableId])
    <div class="table-responsive">
      <table id="{{ $tableId }}" class="table table-hover align-middle text-nowrap mb-0">
        <thead>
          <tr>
            <th class="text-center" style="width: 56px;">No.</th>
            <th class="text-center" style="width: 96px;">Tgl</th>
            <th style="width: 150px;">Pemilik</th>
            <th style="width: 180px;">Nama Barang</th>
            <th class="text-center" style="width: 70px;">Unit</th>
            <th class="text-center" style="width: 80px;">Jumlah</th>
            <th class="text-center" style="width: 120px;">Harga</th>
            <th class="text-center" style="width: 130px;">Total</th>
            <th class="text-center" style="width: 96px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($transaksiList as $transaksi)
            <tr data-date="{{ $transaksi->tanggal->format('Y-m-d') }}" data-owner-code="{{ $transaksi->ownerKode }}">
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="text-center" data-order="{{ $transaksi->tanggal->format('Y-m-d') }}">{{ $transaksi->tanggal->format('d/m/Y') }}</td>
              <td>{{ $transaksi->ownerNama }}</td>
              <td class="fw-semibold">{{ $transaksi->nama_barang }}</td>
              <td class="text-center">{{ $transaksi->satuan }}</td>
              <td class="text-center" data-order="{{ $transaksi->jumlah }}">{{ rtrim(rtrim(number_format((float) $transaksi->jumlah, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-center" data-order="{{ $transaksi->harga }}">Rp {{ number_format((float) $transaksi->harga, 2, ',', '.') }}</td>
              <td class="text-center fw-semibold" data-order="{{ $transaksi->total }}">Rp {{ number_format((float) $transaksi->total, 2, ',', '.') }}</td>
              <td class="text-center">
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-primary" href="{{ route($editRoute, $transaksi->id) }}" aria-label="Edit transaksi {{ $transaksi->nama_barang }}" title="Edit transaksi">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                  </a>
                  <form method="POST" action="{{ route($deleteRoute, $transaksi->id) }}" onsubmit="return confirm('Hapus transaksi {{ $transaksi->nama_barang }} tanggal {{ $transaksi->tanggal->format('d/m/Y') }}? Stok akan disesuaikan.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger" type="submit" aria-label="Hapus transaksi {{ $transaksi->nama_barang }}" title="Hapus transaksi">
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
    'tableId' => $tableId,
    'title' => 'Riwayat '.$jenisTransaksi,
    'emptyMessage' => 'Belum ada riwayat '.strtolower($jenisTransaksi).'.',
    'withExports' => false,
    'nonOrderableColumns' => [0, 8],
])

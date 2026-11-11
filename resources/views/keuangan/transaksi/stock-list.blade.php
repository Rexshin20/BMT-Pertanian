@extends('layouts.modernize')

@section('title', 'Create '.$jenisTransaksi)
@section('page-title', 'Create '.$jenisTransaksi)

@section('content')
<div class="card mb-0">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h5 class="card-title fw-semibold mb-0">Tambah {{ $jenisTransaksi }}</h5>
      <a
        class="btn btn-outline-secondary btn-sm"
        href="{{ route($jenisTransaksi === 'Penjualan' ? 'penjualan.history' : 'pembelian.history') }}"
      >Riwayat {{ $jenisTransaksi }}</a>
    </div>
    @if ($jenisTransaksi === 'Penjualan')
      @include('keuangan.transaksi.forms.penjualan')
    @else
      @include('keuangan.transaksi.forms.pembelian')
    @endif
  </div>
</div>

@endsection

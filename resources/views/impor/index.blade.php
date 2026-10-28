@extends('layouts.modernize')

@section('title', 'Impor '.$label)
@section('page-title', 'Impor Data '.$label)

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <h5 class="card-title fw-semibold mb-0">Upload File</h5>
      <div class="btn-group" role="group" aria-label="Pilih jenis data">
        <a class="btn btn-sm {{ $jenis === 'pembelian' ? 'btn-primary' : 'btn-outline-primary' }}"
          href="{{ route('impor.index', ['jenis' => 'pembelian']) }}">Pembelian</a>
        <a class="btn btn-sm {{ $jenis === 'penjualan' ? 'btn-primary' : 'btn-outline-primary' }}"
          href="{{ route('impor.index', ['jenis' => 'penjualan']) }}">Penjualan</a>
        <a class="btn btn-sm {{ $jenis === 'stok-akhir' ? 'btn-primary' : 'btn-outline-primary' }}"
          href="{{ route('impor.index', ['jenis' => 'stok-akhir']) }}">Stok Akhir</a>
      </div>
    </div>

    <form method="POST" action="{{ route('impor.preview') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
      @csrf
      <input type="hidden" name="jenis" value="{{ $jenis }}">

      <div class="col-md-8">
        <label class="form-label" for="berkas">
          File {{ $label }}
          <span class="text-muted">({{ $jenis === 'stok-akhir' ? '.xlsx, .xls, .csv' : '.xlsx, .xls, .csv, .pdf' }}, maks. 5 MB)</span>
        </label>
        <input class="form-control" id="berkas" type="file" name="berkas" required>
      </div>

      <div class="col-md-4">
        <button class="btn btn-primary w-100" type="submit">
          <i class="ti ti-upload me-1"></i>Upload &amp; Preview
        </button>
      </div>
    </form>

    <div class="alert alert-info d-flex align-items-start gap-2 mt-4 mb-0" role="alert">
      <i class="ti ti-info-circle fs-5"></i>
      <div class="fs-3">Data akan dicek dulu sebelum disimpan. Pemilik yang tidak ditemukan atau angka tidak valid akan dilewati.</div>
    </div>
  </div>
</div>
@endsection

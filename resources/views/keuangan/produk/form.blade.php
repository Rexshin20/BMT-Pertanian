@extends('layouts.modernize')

@php($mengedit = $produk !== null)

@section('title', $mengedit ? 'Edit Barang' : 'Tambah Barang')
@section('page-title', $mengedit ? 'Edit Barang' : 'Tambah Barang')

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h5 class="card-title fw-semibold mb-0">{{ $mengedit ? 'Edit Data Barang' : 'Tambah Barang' }}</h5>
          <a class="btn btn-outline-secondary btn-sm" href="{{ route('penjualan.create.list') }}">Kembali ke Stok Barang</a>
        </div>
        <form method="POST" action="{{ $mengedit ? route('produk.update', $produk) : route('produk.store') }}">
          @csrf
          @if ($mengedit)
            @method('PUT')
          @endif
          <div class="mb-3">
            <label class="form-label" for="pemilik_kode">ID Pemilik</label>
            <select class="form-select" id="pemilik_kode" name="pemilik_kode" required>
              <option value="">Pilih pemilik</option>
              @foreach ($pemiliks as $pemilik)
                <option value="{{ $pemilik->kode }}" @selected(old('pemilik_kode', $produk?->pemilik?->kode ?? $lastPemilikKode) === $pemilik->kode)>{{ $pemilik->id_tampilan }} — {{ $pemilik->nama }}</option>
              @endforeach
            </select>
            @error('pemilik_kode')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="nama">Nama barang</label>
            <input class="form-control" id="nama" name="nama" value="{{ old('nama', $produk?->nama) }}" maxlength="150" required>
            @error('nama')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="satuan">Satuan</label>
            <input class="form-control" id="satuan" name="satuan" value="{{ old('satuan', $produk?->satuan ?? 'pcs') }}" maxlength="30" required>
            @error('satuan')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="mb-4">
            <label class="form-label" for="harga_jual">Harga jual standar (opsional)</label>
            <input class="form-control" type="number" id="harga_jual" name="harga_jual" value="{{ old('harga_jual', $produk?->harga_jual) }}" min="0" max="9999999.99" step="0.01">
            @error('harga_jual')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          @if ($mengedit)
            <p class="text-muted fs-3">Mengubah nama atau pemilik barang juga mengubah nama/pemilik yang tampil pada riwayat transaksi lama.</p>
          @else
            <p class="text-muted fs-3">Stok bertambah saat transaksi pembelian dicatat.</p>
          @endif
          <button class="btn btn-primary w-100" type="submit">{{ $mengedit ? 'Simpan Perubahan' : 'Simpan Barang' }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

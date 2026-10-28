@extends('layouts.modernize')

@section('title', 'Master ID Nama')
@section('page-title', 'Master ID Nama')

@section('content')
<div class="row">
  <div class="col-lg-4 col-xl-3">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Tambah Nama</h5>
        <form method="POST" action="{{ route('pemilik.store') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="nama">Nama pemilik <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
            <input class="form-control" id="nama" name="nama" value="{{ old('nama') }}" maxlength="150" required>
            @error('nama')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="alamat">Alamat / Desa</label>
            <textarea class="form-control" id="alamat" name="alamat" rows="2" maxlength="255" placeholder="Contoh: Dsn. Dawung Kec. Grabagan Kab. Tuban">{{ old('alamat') }}</textarea>
            @error('alamat')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
          </div>
          <button class="btn btn-primary w-100" type="submit">Simpan Nama</button>
        </form>
        <p class="text-muted fs-3 mt-3 mb-0">Masukkan Nama dan Alamat pemilik. ID dibuat otomatis.</p>
      </div>
    </div>
  </div>
  <div class="col-lg-8 col-xl-9">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Daftar Nama</h5>
        <div class="table-responsive">
          <table id="pemilik-table" class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="text-center text-nowrap" style="width: 64px;">ID</th>
                <th style="min-width: 140px;">Nama</th>
                <th style="min-width: 170px;">Alamat / Desa</th>
                <th class="text-end text-nowrap" style="width: 110px;">Jumlah Barang</th>
                <th class="text-end text-nowrap" style="width: 120px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($pemiliks as $pemilik)
                <tr>
                  <td class="text-center fw-semibold text-nowrap">{{ $pemilik->id_tampilan }}</td>
                  <td class="text-break">{{ $pemilik->nama }}</td>
                  <td class="text-muted text-break">{{ $pemilik->alamat ?? '-' }}</td>
                  <td class="text-end text-nowrap">{{ $pemilik->produks_count }}</td>
                  <td class="text-end text-nowrap">
                    <div class="d-inline-flex gap-1">
                      <a class="btn btn-sm btn-primary" href="{{ route('pemilik.edit', $pemilik) }}" aria-label="Edit {{ $pemilik->nama }}" title="Edit {{ $pemilik->nama }}">
                        <i class="ti ti-edit" aria-hidden="true"></i>
                      </a>
                      <form method="POST" action="{{ route('pemilik.destroy', $pemilik) }}" onsubmit="return confirm('Hapus pemilik {{ $pemilik->nama }}? Semua barang dan seluruh transaksi terkait akan dihapus permanen.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit" aria-label="Hapus {{ $pemilik->nama }}" title="Hapus {{ $pemilik->nama }}">
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
  </div>
</div>
@endsection

@include('keuangan.partials.data-table', [
    'tableId' => 'pemilik-table',
    'title' => 'Master ID Nama',
    'emptyMessage' => 'Belum ada nama pemilik.',
    'withExports' => false,
    'renumberFirstColumn' => false,
    'nonOrderableColumns' => [3],
])

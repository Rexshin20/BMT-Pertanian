@extends('layouts.modernize')

@section('title', 'Preview Impor Pembelian')
@section('page-title', 'Preview Impor Pembelian')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h5 class="card-title fw-semibold mb-1">Periksa data sebelum disimpan</h5>
        <span class="badge bg-success">{{ $validCount }} valid</span>
        <span class="badge bg-danger">{{ count($rows) - $validCount }} bermasalah (akan dilewati)</span>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('pembelian.create') }}">Batal</a>
        @if ($validCount > 0)
          <form method="POST" action="{{ route('pembelian.import.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button class="btn btn-primary" type="submit">Simpan {{ $validCount }} baris</button>
          </form>
        @endif
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Baris</th><th>ID Pemilik</th><th>Nama Barang</th><th>Tanggal</th><th>Satuan</th>
            <th class="text-end">Jumlah</th><th class="text-end">Harga Beli</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $row)
            <tr class="{{ $row['errors'] ? 'table-danger' : '' }}">
              <td>{{ $row['line'] }}</td>
              <td>{{ $row['data']['pemilik_kode'] }}</td>
              <td>{{ $row['data']['nama_barang'] }}</td>
              <td>{{ $row['data']['tanggal'] }}</td>
              <td>{{ $row['data']['satuan'] ?: '-' }}</td>
              <td class="text-end">{{ $row['data']['jumlah'] }}</td>
              <td class="text-end">{{ $row['data']['harga_beli'] }}</td>
              <td>
                @if ($row['errors'])
                  <ul class="mb-0 ps-3 text-danger">
                    @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                  </ul>
                @else
                  <span class="text-success">OK</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

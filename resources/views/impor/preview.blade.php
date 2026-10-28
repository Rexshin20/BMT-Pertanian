@extends('layouts.modernize')

@section('title', 'Preview Impor '.$label)
@section('page-title', 'Preview Impor '.$label)

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h5 class="card-title fw-semibold mb-1">Periksa data {{$label}} sebelum disimpan</h5>
        <span class="badge bg-success">{{ $validCount }} valid</span>
        <span class="badge bg-danger">{{ count($rows) - $validCount }} bermasalah (akan dilewati)</span>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('impor.index', ['jenis' => $jenis]) }}">Batal</a>
        @if ($validCount > 0)
          <form method="POST" action="{{ route('impor.simpan') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button class="btn btn-primary" type="submit">Simpan {{ $validCount }} baris {{$label}}</button>
          </form>
        @endif
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          @if ($jenis === 'stok-akhir')
            <tr>
              <th>NO</th><th>Nama Pemilik</th><th>ID Pemilik</th><th>Nama Barang</th><th>Tanggal</th>
              <th>Berat Bersih</th><th class="text-end">Stok Barang</th><th class="text-end">Barang Terjual</th>
              <th class="text-end">Stok Akhir</th>
            </tr>
          @else
            <tr>
              <th>Baris</th><th>ID Pemilik</th><th>Nama Barang</th><th>Tanggal</th><th>Satuan</th>
              <th class="text-end">Jumlah</th><th class="text-end">Harga</th><th>Status</th>
            </tr>
          @endif
        </thead>
        <tbody>
          @foreach ($rows as $row)
            @php($data = $row['data'])
            <tr class="{{ $row['errors'] ? 'table-danger' : '' }}">
              @if ($jenis === 'stok-akhir')
                <td>{{ $loop->iteration }}</td>
                <td>{{ $data['nama_pemilik'] ?? '' }}</td>
                <td>{{ $data['pemilik_kode'] }}</td>
                <td class="fw-semibold">{{ $data['nama_barang'] }}</td>
                <td>{{ $data['tanggal'] }}</td>
                <td>{{ $data['berat_bersih'] ?? '' }}</td>
                <td class="text-end">{{ rtrim(rtrim($data['stok_barang'] ?? '0', '0'), '.') ?: '0' }}</td>
                <td class="text-end">{{ rtrim(rtrim($data['barang_terjual'] ?? '0', '0'), '.') ?: '0' }}</td>
                <td class="text-end fw-semibold">{{ rtrim(rtrim($data['stok_akhir'] ?? '0', '0'), '.') ?: '0' }}</td>
              @else
                <td>{{ $row['line'] }}</td>
                <td>{{ $data['pemilik_kode'] }}</td>
                <td>{{ $data['nama_barang'] }}</td>
                <td>{{ $data['tanggal'] }}</td>
                <td>{{ $data['satuan'] }}</td>
                <td class="text-end">{{ $data['jumlah'] }}</td>
                <td class="text-end">{{ $data['harga_beli'] }}</td>
                <td>
                  @if ($row['errors'])
                    <ul class="mb-0 ps-3 text-danger">
                      @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                  @else
                    <span class="text-success">OK</span>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if ($jenis === 'stok-akhir')
      @php($bermasalah = collect($rows)->filter(fn ($row) => $row['errors'] !== [])->pluck('line'))
      @if ($bermasalah->isNotEmpty())
        <div class="alert alert-warning py-2 mb-0 mt-3 small">
          Baris bermasalah (akan dilewati): {{ $bermasalah->implode(', ') }}
        </div>
      @endif
    @endif
  </div>
</div>
@endsection

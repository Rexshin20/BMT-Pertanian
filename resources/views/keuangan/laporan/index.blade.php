@extends('layouts.modernize')

@section('title', 'Laporan Laba Rugi')
@section('page-title', 'Laporan Laba Rugi')

@push('styles')
<style>
  #laba-rugi-table th:last-child,
  #laba-rugi-table td:last-child {
    position: sticky;
    right: 0;
    z-index: 2;
    background-color: var(--bs-body-bg, #fff);
    box-shadow: -6px 0 8px rgb(0 0 0 / 8%);
  }

  #laba-rugi-table th:last-child {
    z-index: 3;
  }
</style>
@endpush

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Total Harga Beli</h6>
      <h4 class="fw-semibold mb-0">Rp {{ number_format((float) $ringkasan->total_hb, 2, ',', '.') }}</h4>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Total Harga Jual</h6>
      <h4 class="fw-semibold mb-0">Rp {{ number_format((float) $ringkasan->total_hj, 2, ',', '.') }}</h4>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-body">
      <h6 class="text-muted fw-semibold">Laba / Rugi</h6>
      <h4 class="fw-semibold mb-0 {{ (float) $ringkasan->total_laba < 0 ? 'text-danger' : 'text-success' }}">Rp {{ number_format((float) $ringkasan->total_laba, 2, ',', '.') }}</h4>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Rincian Laba Rugi</h5>
        <span class="text-muted fs-3">{{ \Illuminate\Support\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}</span>
      </div>
    </div>
    <form method="GET" action="{{ route('laporan.laba-rugi') }}" class="row align-items-end g-3 mb-3">
      <div class="col-sm-6 col-md-4">
        <label class="form-label" for="laba-rugi-table-owner-filter">Tampilkan transaksi milik</label>
        <select class="form-select" id="laba-rugi-table-owner-filter" name="pemilik" data-auto-submit-owner-filter>
          <option value="">Semua nama</option>
          @foreach ($pemiliks as $pemilik)
            <option value="{{ $pemilik->kode }}" @selected($pemilikTerpilih === $pemilik->kode)>{{ $pemilik->id_tampilan }} — {{ $pemilik->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-sm-6 col-md-4">
        <label class="form-label" for="laba-rugi-table-date-from">Tanggal dari</label>
        <input class="form-control" type="date" id="laba-rugi-table-date-from" name="tanggal_mulai" value="{{ $tanggalMulai }}" onchange="this.form.requestSubmit()">
      </div>
      <div class="col-sm-6 col-md-4">
        <label class="form-label" for="laba-rugi-table-date-to">Tanggal sampai</label>
        <input class="form-control" type="date" id="laba-rugi-table-date-to" name="tanggal_selesai" value="{{ $tanggalSelesai }}" onchange="this.form.requestSubmit()">
      </div>
    </form>
    <div class="table-responsive">
      <table id="laba-rugi-table" class="table table-hover align-middle text-nowrap mb-0">
        <thead><tr><th class="text-center" style="width: 56px;">No.</th><th class="text-center" style="width: 96px;">Tgl</th><th style="min-width: 240px;">Nama Barang</th><th class="text-center" style="width: 80px;">Unit</th><th class="text-center" style="width: 130px;">Harga Beli</th><th class="text-center" style="width: 130px;">Total Beli</th><th class="text-center" style="width: 130px;">Harga Jual</th><th class="text-center" style="width: 130px;">Total Jual</th><th class="text-center" style="width: 140px;">Laba / Rugi</th></tr></thead>
        <tbody>
          @foreach ($penjualans as $penjualan)
            <tr data-date="{{ $penjualan->tanggal->format('Y-m-d') }}" data-owner-code="{{ $penjualan->ownerKode ?? '' }}">
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="text-center" data-order="{{ $penjualan->tanggal->format('Y-m-d') }}">{{ $penjualan->tanggal->format('d/m/Y') }}</td>
              <td class="fw-semibold">{{ $penjualan->nama_barang }}@if (filled($penjualan->satuan)) <span class="text-muted fw-normal">({{ $penjualan->satuan }})</span>@endif</td>
              <td class="text-center" data-order="{{ $penjualan->jumlah }}">{{ rtrim(rtrim(number_format((float) $penjualan->jumlah, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-center" data-order="{{ $penjualan->harga_beli }}">Rp {{ number_format((float) $penjualan->harga_beli, 2, ',', '.') }}</td>
              <td class="text-center" data-order="{{ (float) $penjualan->harga_beli * (float) $penjualan->jumlah }}">Rp {{ number_format((float) $penjualan->harga_beli * (float) $penjualan->jumlah, 2, ',', '.') }}</td>
              <td class="text-center" data-order="{{ $penjualan->harga_jual }}">Rp {{ number_format((float) $penjualan->harga_jual, 2, ',', '.') }}</td>
              <td class="text-center" data-order="{{ (float) $penjualan->harga_jual * (float) $penjualan->jumlah }}">Rp {{ number_format((float) $penjualan->harga_jual * (float) $penjualan->jumlah, 2, ',', '.') }}</td>
              <td class="text-center fw-semibold {{ (float) $penjualan->laba < 0 ? 'text-danger' : 'text-success' }}" data-order="{{ $penjualan->laba }}" title="{{ (float) $penjualan->laba < 0 ? 'Rugi' : 'Laba' }}">Rp {{ number_format((float) $penjualan->laba, 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@include('keuangan.partials.export-buttons', [
    'tableId' => 'laba-rugi-table',
    'judulExport' => 'LABA RUGI',
    'prefixFile' => 'Laba-Rugi',
    'orientasi' => 'landscape',
    'kolomHuruf' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
    'tipeKolom' => ['A' => 'num', 'B' => 'text', 'C' => 'text', 'D' => 'num', 'E' => 'num', 'F' => 'num', 'G' => 'num', 'H' => 'num', 'I' => 'num'],
    'lebarExcel' => [6, 12, 32, 10, 15, 16, 15, 16, 15],
    'lebarPdf' => ['4%', '9%', '24%', '6%', '11%', '12%', '11%', '12%', '11%'],
    'rataKiri' => [2],
])

@include('keuangan.partials.data-table', [
    'tableId' => 'laba-rugi-table',
    'title' => $pemilikTerpilihNama ? 'Laporan Laba Rugi - '.$pemilikTerpilihNama : 'Laporan Laba Rugi',
    'emptyMessage' => 'Tidak ada penjualan pada periode ini.',
])

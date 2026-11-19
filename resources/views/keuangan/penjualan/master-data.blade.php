@extends('layouts.modernize')

@section('title', 'View Penjualan')
@section('page-title', 'View Penjualan')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Data Penjualan</h5>
        <span class="text-muted fs-3">Halaman ini hanya untuk melihat riwayat penjualan.</span>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('laporan.laba-rugi') }}" class="btn btn-outline-primary">Laporan Laba Rugi</a>
      </div>
    </div>
    @include('keuangan.partials.date-filter', ['tableId' => 'penjualan-table'])
    <div class="table-responsive">
      <table id="penjualan-table" class="table table-hover align-middle text-nowrap mb-0">
        <thead>
          <tr>
            <th class="text-center" style="width: 56px;">No.</th>
            <th class="text-center" style="width: 96px;">Tgl</th>
            <th style="min-width: 240px;">Nama Barang</th>
            <th class="text-center" style="width: 90px;">Unit</th>
            <th class="text-center" style="width: 130px;">Harga</th>
            <th class="text-center" style="width: 140px;">Total</th>
            <th class="d-none">Berat Bersih</th>
            <th class="d-none">Harga Beli</th>
            <th class="d-none">Harga Jual</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($penjualans as $penjualan)
            <tr data-date="{{ $penjualan->tanggal->format('Y-m-d') }}" data-owner-code="{{ $penjualan->ownerKode ?? '' }}" data-nama="{{ $penjualan->nama_barang }}" data-satuan="{{ $penjualan->satuan }}" data-harga-jual="{{ $penjualan->harga }}" data-harga-beli="{{ $penjualan->harga_beli }}">
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="text-center" data-order="{{ $penjualan->tanggal->format('Y-m-d') }}">{{ $penjualan->tanggal->format('d/m/Y') }}</td>
              <td class="fw-semibold" data-nama="{{ $penjualan->nama_barang }}">{{ $penjualan->nama_barang }}@if (filled($penjualan->satuan)) <span class="text-muted fw-normal">({{ $penjualan->satuan }})</span>@endif</td>
              <td class="text-center" data-order="{{ $penjualan->jumlah }}">{{ rtrim(rtrim(number_format((float) $penjualan->jumlah, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-center" data-order="{{ $penjualan->harga }}">Rp {{ number_format((float) $penjualan->harga, 2, ',', '.') }}</td>
              <td class="text-center fw-semibold" data-order="{{ (float) $penjualan->harga * (float) $penjualan->jumlah }}">Rp {{ number_format((float) $penjualan->harga * (float) $penjualan->jumlah, 2, ',', '.') }}</td>
              <td class="d-none">{{ $penjualan->satuan }}</td>
              <td class="d-none">Rp {{ number_format((float) $penjualan->harga_beli, 2, ',', '.') }}</td>
              <td class="d-none">Rp {{ number_format((float) $penjualan->harga, 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@include('keuangan.partials.export-buttons', [
    'tableId' => 'penjualan-table',
    'judulExport' => 'PENJUALAN',
    'prefixFile' => 'Penjualan',
    'kolomHuruf' => ['A', 'B', 'C', 'D', 'E', 'F'],
    'tipeKolom' => ['A' => 'num', 'B' => 'text', 'C' => 'text', 'D' => 'num', 'E' => 'num', 'F' => 'num'],
    'lebarExcel' => [6, 36, 12, 10, 18, 18],
    'pdfKolomKhusus' => ['No.', 'Nama Barang', 'Berat Bersih', 'Unit', 'Harga Beli', 'Harga Jual'],
    'excelKolomKhusus' => [0, 2, 6, 3, 7, 8],
    'lebarPdf' => ['6%', '30%', '14%', '8%', '21%', '21%'],
    'rataKiri' => [1],
])

@include('keuangan.partials.data-table', [
    'tableId' => 'penjualan-table',
    'title' => $pemilikTerpilihNama ? 'Data Penjualan - '.$pemilikTerpilihNama : 'Data Penjualan',
    'emptyMessage' => 'Belum ada data penjualan.',
])

@extends('layouts.modernize')

@section('title', 'View Pembelian')
@section('page-title', 'View Pembelian')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Data Pembelian</h5>
        <span class="text-muted fs-3">Halaman ini hanya untuk melihat riwayat pembelian.</span>
      </div>
    </div>
    @include('keuangan.partials.date-filter', ['tableId' => 'pembelian-table'])
    <div class="table-responsive">
      <table id="pembelian-table" class="table table-hover align-middle text-nowrap mb-0">
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
          @foreach ($pembelians as $pembelian)
            <tr data-date="{{ $pembelian->tanggal->format('Y-m-d') }}" data-owner-code="{{ $pembelian->ownerKode ?? '' }}" data-nama="{{ $pembelian->nama_barang }}" data-satuan="{{ $pembelian->satuan }}" data-harga-jual="{{ $pembelian->harga_jual }}" data-harga-beli="{{ $pembelian->harga }}">
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="text-center" data-order="{{ $pembelian->tanggal->format('Y-m-d') }}">{{ $pembelian->tanggal->format('d/m/Y') }}</td>
              <td class="fw-semibold" data-nama="{{ $pembelian->nama_barang }}">{{ $pembelian->nama_barang }}@if (filled($pembelian->satuan)) <span class="text-muted fw-normal">({{ $pembelian->satuan }})</span>@endif</td>
              <td class="text-center" data-order="{{ $pembelian->jumlah }}">{{ rtrim(rtrim(number_format((float) $pembelian->jumlah, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-center" data-order="{{ $pembelian->harga }}">Rp {{ number_format((float) $pembelian->harga, 2, ',', '.') }}</td>
              <td class="text-center fw-semibold" data-order="{{ (float) $pembelian->harga * (float) $pembelian->jumlah }}">Rp {{ number_format((float) $pembelian->harga * (float) $pembelian->jumlah, 2, ',', '.') }}</td>
              <td class="d-none">{{ $pembelian->satuan }}</td>
              <td class="d-none">Rp {{ number_format((float) $pembelian->harga, 2, ',', '.') }}</td>
              <td class="d-none">Rp {{ number_format((float) $pembelian->harga_jual, 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@include('keuangan.partials.export-buttons', [
    'tableId' => 'pembelian-table',
    'judulExport' => 'TANDA TERIMA',
    'prefixFile' => 'Pembelian',
    'kolomHuruf' => ['A', 'B', 'C', 'D', 'E', 'F'],
    'tipeKolom' => ['A' => 'num', 'B' => 'text', 'C' => 'text', 'D' => 'num', 'E' => 'num', 'F' => 'num'],
    'lebarExcel' => [6, 36, 12, 10, 18, 18],
    'pdfKolomKhusus' => ['No.', 'Nama Barang', 'Berat Bersih', 'Unit', 'Harga Beli', 'Harga Jual'],
    'excelKolomKhusus' => [0, 2, 6, 3, 7, 8],
    'lebarPdf' => ['6%', '30%', '14%', '8%', '21%', '21%'],
    'rataKiri' => [1],
])

@include('keuangan.partials.data-table', [
    'tableId' => 'pembelian-table',
    'title' => $pemilikTerpilihNama ? 'Data Pembelian - '.$pemilikTerpilihNama : 'Data Pembelian',
    'emptyMessage' => 'Belum ada data pembelian.',
])

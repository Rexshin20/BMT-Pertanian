@extends('layouts.modernize')

@section('title', 'View Pembelian')
@section('page-title', 'View Pembelian')

@section('content')
<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
      <div>
        <h5 class="card-title fw-semibold mb-1">Data Pembelian</h5>
        <span class="text-muted fs-3">Pembelian dikelompokkan per pemilik; pilih nama untuk melihat atau mengekspor datanya saja.</span>
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
          </tr>
        </thead>
        <tbody>
          @foreach ($pembelians as $pembelian)
            <tr data-date="{{ $pembelian->tanggal->format('Y-m-d') }}" data-owner-code="{{ $pembelian->ownerKode ?? '' }}">
              <td class="text-center">{{ $loop->iteration }}</td>
              <td class="text-center" data-order="{{ $pembelian->tanggal->format('Y-m-d') }}">{{ $pembelian->tanggal->format('d/m/Y') }}</td>
              <td class="fw-semibold">{{ $pembelian->nama_barang }}@if (filled($pembelian->satuan)) <span class="text-muted fw-normal">({{ $pembelian->satuan }})</span>@endif</td>
              <td class="text-center" data-order="{{ $pembelian->jumlah }}">{{ rtrim(rtrim(number_format((float) $pembelian->jumlah, 3, ',', '.'), '0'), ',') }}</td>
              <td class="text-center" data-order="{{ $pembelian->harga }}">Rp {{ number_format((float) $pembelian->harga, 2, ',', '.') }}</td>
              <td class="text-center fw-semibold" data-order="{{ (float) $pembelian->harga * (float) $pembelian->jumlah }}">Rp {{ number_format((float) $pembelian->harga * (float) $pembelian->jumlah, 2, ',', '.') }}</td>
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
    'judulExport' => 'PEMBELIAN',
    'prefixFile' => 'Pembelian',
    'kolomHuruf' => ['A', 'B', 'C', 'D', 'E', 'F'],
    'tipeKolom' => ['A' => 'num', 'B' => 'text', 'C' => 'text', 'D' => 'num', 'E' => 'num', 'F' => 'num'],
    'lebarExcel' => [6, 12, 36, 10, 18, 16],
    'lebarPdf' => ['6%', '12%', '36%', '10%', '18%', '18%'],
    'rataKiri' => [2],
])

@include('keuangan.partials.data-table', [
    'tableId' => 'pembelian-table',
    'title' => $pemilikTerpilihNama ? 'Data Pembelian - '.$pemilikTerpilihNama : 'Data Pembelian',
    'emptyMessage' => 'Belum ada data pembelian.',
])

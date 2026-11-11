@extends('layouts.modernize')

@section('title', 'Edit '.$jenisTransaksi)
@section('page-title', 'Edit '.$jenisTransaksi)

@section('content')
<div class="card">
  <div class="card-body">
    <h5 class="card-title fw-semibold mb-4">Edit Transaksi {{ $jenisTransaksi }}</h5>
    <p class="text-muted">Perubahan jumlah akan menyesuaikan stok secara otomatis.</p>

    @if ($errors->any())
      <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route($updateRoute, $transaksi) }}">
      @csrf
      @method('PUT')
      <div class="row g-3">
        @php($produkTransaksi = $transaksi->produk)
        <div class="col-12">
          <label for="produk_info" class="form-label">Pemilik / Barang</label>
          <input
            id="produk_info"
            type="text"
            class="form-control"
            value="{{ ($produkTransaksi->pemilik?->id_tampilan ?? '-').' — '.($produkTransaksi->pemilik?->nama ?? 'Tanpa pemilik').' / '.$produkTransaksi->nama.' ('.$produkTransaksi->satuan.')' }}"
            readonly
          >
          <small class="text-muted">Barang transaksi tidak bisa diganti dari halaman ini.</small>
          <input type="hidden" name="produk_id" value="{{ old('produk_id', $transaksi->produk_id) }}">
          @error('produk_id')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label for="tanggal" class="form-label">Tanggal</label>
          <input id="tanggal" name="tanggal" type="date" class="form-control" value="{{ old('tanggal', $transaksi->tanggal->format('Y-m-d')) }}" required>
        </div>
        <div class="col-md-3">
          <label for="satuan" class="form-label">Satuan</label>
          <input id="satuan" name="satuan" type="text" maxlength="30" class="form-control" value="{{ old('satuan', $transaksi->satuan ?: $transaksi->produk->satuan) }}" required>
          @error('satuan')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label for="jumlah" class="form-label">Jumlah</label>
          <input id="jumlah" name="jumlah" type="number" class="form-control" min="0.001" max="999999.999" step="0.001" value="{{ old('jumlah', rtrim(rtrim((string) $transaksi->jumlah, '0'), '.')) }}" required>
          @error('jumlah')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
          <label for="{{ $hargaField }}" class="form-label">{{ $hargaLabel }}</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input
              id="{{ $hargaField }}_display"
              type="text"
              class="form-control"
              value="{{ number_format((float) old($hargaField, $transaksi->{$hargaField}), 2, ',', '.') }}"
              inputmode="decimal"
              autocomplete="off"
              required
            >
          </div>
          <input id="{{ $hargaField }}" name="{{ $hargaField }}" type="hidden" value="{{ old($hargaField, $transaksi->{$hargaField}) }}">
          @error($hargaField)<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="{{ route($backRoute) }}" class="btn btn-light">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (() => {
    const display = document.getElementById(@json($hargaField.'_display'));
    const value = document.getElementById(@json($hargaField));

    const normalizePrice = (input) => {
      const cleaned = input.replace(/[^\d,]/g, '');
      const [whole = '', ...decimalParts] = cleaned.split(',');
      const integer = whole.replace(/^0+(?=\d)/, '');
      const decimal = decimalParts.join('').slice(0, 2);
      const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

      return grouped + (cleaned.includes(',') ? ',' + decimal : '');
    };

    const updateValue = () => {
      const normalized = display.value.replace(/\./g, '').replace(',', '.');
      const amount = Number(normalized);
      value.value = Number.isFinite(amount) && amount > 0 ? amount.toFixed(2) : '';
    };

    display.addEventListener('input', () => {
      const cursor = display.selectionStart;
      const previous = display.value;
      display.value = normalizePrice(previous);
      const position = Math.max(0, cursor + display.value.length - previous.length);
      display.setSelectionRange(position, position);
      updateValue();
    });

    updateValue();
  })();
</script>
@endpush

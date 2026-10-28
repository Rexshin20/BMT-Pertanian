@isset($pemiliks)
  <form method="GET" action="{{ url()->current() }}" class="row align-items-end g-3 mb-3" data-owner-filter-form="{{ $tableId }}">
    <input type="hidden" id="{{ $tableId }}-date-from-query" name="tanggal_mulai" value="{{ $tanggalMulai ?? '' }}">
    <input type="hidden" id="{{ $tableId }}-date-to-query" name="tanggal_selesai" value="{{ $tanggalSelesai ?? '' }}">
    <div class="col-sm-6 col-md-4">
      <label class="form-label" for="{{ $tableId }}-owner-filter">Tampilkan transaksi milik</label>
      <select class="form-select" id="{{ $tableId }}-owner-filter" name="pemilik" data-auto-submit-owner-filter>
        <option value="">Semua nama</option>
        @foreach ($pemiliks as $pemilik)
          <option value="{{ $pemilik->kode }}" @selected($pemilikTerpilih === $pemilik->kode)>{{ $pemilik->id_tampilan }} — {{ $pemilik->nama }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-sm-6 col-md-4">
      <label class="form-label" for="{{ $tableId }}-date-from">Tanggal dari</label>
      <input class="form-control" type="date" id="{{ $tableId }}-date-from" value="{{ $tanggalMulai ?? '' }}">
    </div>
    <div class="col-sm-6 col-md-4">
      <label class="form-label" for="{{ $tableId }}-date-to">Tanggal sampai</label>
      <input class="form-control" type="date" id="{{ $tableId }}-date-to" value="{{ $tanggalSelesai ?? '' }}">
    </div>
  </form>
@else
  <div class="row align-items-end g-3 mb-3">
    <div class="col-sm-6 col-md-4">
      <label class="form-label" for="{{ $tableId }}-date-from">Tanggal dari</label>
      <input class="form-control" type="date" id="{{ $tableId }}-date-from" value="{{ $tanggalMulai ?? '' }}">
    </div>
    <div class="col-sm-6 col-md-4">
      <label class="form-label" for="{{ $tableId }}-date-to">Tanggal sampai</label>
      <input class="form-control" type="date" id="{{ $tableId }}-date-to" value="{{ $tanggalSelesai ?? '' }}">
    </div>
  </div>
@endisset

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap-5-theme.min.css') }}">
<style>
  .select2-container--bootstrap-5 .select2-selection {
    min-height: calc(1.5em + 0.75rem + 2px);
    border-color: #dfe3e8;
    border-radius: 0.375rem;
  }

  .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: #2a3547;
  }

  .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    top: 0;
    right: 0.75rem;
    height: 100%;
  }

  .select2-container--bootstrap-5.select2-container--focus .select2-selection,
  .select2-container--bootstrap-5.select2-container--open .select2-selection {
    border-color: #16834a;
    box-shadow: 0 0 0 0.2rem rgba(22, 131, 74, 0.18);
  }

  .select2-container--bootstrap-5 .select2-dropdown {
    border-color: #dfe3e8;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08);
  }

  .select2-container--bootstrap-5 .select2-search--dropdown {
    padding: 0.5rem 0.75rem 0;
  }

  .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field {
    border-color: #dfe3e8;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
  }

  .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field:focus {
    border-color: #16834a;
    box-shadow: 0 0 0 0.2rem rgba(22, 131, 74, 0.18);
    outline: 0;
  }

  .select2-container--bootstrap-5 .select2-results__option {
    padding: 0.5rem 0.75rem;
  }

  .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option.select2-results__option--highlighted {
    background-color: #e7f5ec;
    color: #126b3c;
  }

  .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option.select2-results__option--selected,
  .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option[aria-selected=true]:not(.select2-results__option--highlighted) {
    background-color: #16834a;
    color: #fff;
  }

  .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option.select2-results__option--selected.select2-results__option--highlighted {
    background-color: #126b3c;
    color: #fff;
  }
</style>
@endpush

<form method="POST" action="{{ route('penjualan.store') }}">
  @csrf
  <div class="row g-2 mb-2">
    <div class="col-md-6">
      <label class="form-label" for="pemilik_kode">ID Pemilik <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
      <input class="form-control" type="text" id="pemilik_kode" name="pemilik_kode" value="{{ old('pemilik_kode', $lastPemilikKode) }}" inputmode="numeric" maxlength="10" list="daftar-id-pemilik" autocomplete="off" required>
      <datalist id="daftar-id-pemilik">
        @foreach ($pemiliks as $pemilik)
          <option value="{{ $pemilik->kode }}">{{ $pemilik->nama }}</option>
        @endforeach
      </datalist>
      @error('pemilik_kode')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
      <label class="form-label" for="pemilik_nama">Nama Pemilik</label>
      <input class="form-control" type="text" id="pemilik_nama" value="{{ $pemiliks->firstWhere('kode', old('pemilik_kode', $lastPemilikKode))?->nama ?? '' }}" placeholder="Nama muncul otomatis setelah ID dimasukkan" readonly>
      <small class="text-muted" id="nama-pemilik">Masukkan ID dari Master ID Nama.</small>
    </div>
  </div>
  <div class="mb-2">
    <label class="form-label" for="produk_id">Nama Barang <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
    <select class="form-select" id="produk_id" name="produk_id" disabled required>
      <option value="">Masukkan ID pemilik terlebih dahulu</option>
      @foreach ($produksTersedia as $produk)
        <option value="{{ $produk->id }}" data-owner-code="{{ $produk->pemilik->kode ?? '' }}" data-unit="{{ $produk->satuan }}" @selected((string) old('produk_id') === (string) $produk->id)>
          {{ $produk->nama }} — stok {{ rtrim(rtrim(number_format((float) $produk->stok, 3, ',', '.'), '0'), ',') }} {{ $produk->satuan }}
        </option>
      @endforeach
    </select>
    <small class="text-muted d-block mt-1" id="stok-pemilik">Pilih pemilik untuk melihat stok barangnya.</small>
    @error('produk_id')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
  </div>
  <div class="row g-2 mb-2">
    <div class="col-6 col-md-2">
      <label class="form-label" for="nomor">No.</label>
      <input class="form-control" id="nomor" value="{{ $nomorBerikutnya }}" readonly>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="tanggal">Tgl <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
      <input class="form-control" type="date" id="tanggal" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" required>
      @error('tanggal')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-4 col-md-2">
      <label class="form-label" for="satuan">Unit <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
      <input class="form-control" type="text" id="satuan" name="satuan" value="{{ old('satuan') }}" maxlength="30" required>
      @error('satuan')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-8 col-md-3">
      <label class="form-label" for="harga_jual">Harga jual <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
      <div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" type="text" id="harga_jual_display" value="{{ old('harga_jual') ? number_format((float) old('harga_jual'), 2, ',', '.') : '' }}" inputmode="decimal" autocomplete="off" required><input type="hidden" id="harga_jual" name="harga_jual" value="{{ old('harga_jual') }}"></div>
      <small class="text-muted">Per unit</small>
      @error('harga_jual')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="col-4 col-md-3">
      <label class="form-label" for="jumlah">Jumlah <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> wajib diisi</span></label>
      <input class="form-control" type="number" id="jumlah" name="jumlah" value="{{ old('jumlah') }}" min="0.001" max="999999.999" step="0.001" required>
      @error('jumlah')<div class="text-danger fs-3 mt-1">{{ $message }}</div>@enderror
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="total_jumlah">Total harga penjualan (Jumlah × Harga)</label>
    <div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" id="total_jumlah" value="0,00" readonly></div>
  </div>
  <button class="btn btn-primary w-100" type="submit">Simpan Penjualan</button>
</form>

@push('scripts')
<script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
<script>
  (() => {
    const ownerInput = document.getElementById('pemilik_kode');
    const ownerName = document.getElementById('nama-pemilik');
    const ownerNameField = document.getElementById('pemilik_nama');
    const ownerNames = @json($pemiliks->pluck('nama', 'kode'));
    const productSelect = document.getElementById('produk_id');
    const stockHint = document.getElementById('stok-pemilik');
    const unitInput = document.getElementById('satuan');
    const quantityInput = document.getElementById('jumlah');
    const priceInput = document.getElementById('harga_jual');
    const priceDisplay = document.getElementById('harga_jual_display');
    const totalInput = document.getElementById('total_jumlah');
    const allProducts = Array.from(productSelect.options)
      .filter((option) => option.value)
      .map((option) => ({
        id: option.value,
        label: option.textContent.trim(),
        ownerCode: option.dataset.ownerCode,
        unit: option.dataset.unit,
      }));

    const parsePrice = (value) => {
      const normalized = value.replace(/\./g, '').replace(',', '.').replace(/[^\d.]/g, '');
      const amount = Number(normalized);

      return Number.isFinite(amount) ? amount : 0;
    };

    const formatPrice = (value) => {
      const cleaned = value.replace(/[^\d,]/g, '');
      const [whole = '', ...decimalParts] = cleaned.split(',');
      const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      const hasDecimal = cleaned.includes(',');
      const decimal = decimalParts.join('').slice(0, 2);

      return groupedWhole + (hasDecimal ? ',' + decimal : '');
    };

    const updateTotal = () => {
      const quantity = Number(quantityInput.value);
      const price = parsePrice(priceDisplay.value);
      priceInput.value = price > 0 ? price.toFixed(2) : '';
      totalInput.value = (quantity > 0 && price > 0)
        ? new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(quantity * price)
        : '0,00';
    };

    const ownerCode = () => ownerInput.value.trim().padStart(3, '0');
    let currentOwnerCode = null;

    const initSelect2 = () => {
      if (!window.jQuery?.fn?.select2) {
        return;
      }

      window.jQuery(productSelect).select2({
        theme: 'bootstrap-5',
        width: '100%',
        language: {
          noResults: () => 'Barang tidak ditemukan',
          searching: () => 'Mencari...',
        },
      });

      window.jQuery(productSelect).off('select2:open.ui').on('select2:open.ui', () => {
        document.querySelector('.select2-search__field')?.setAttribute('placeholder', 'Cari nama barang...');
      });
    };

    const rebuildProductSelect = (code) => {
      const matches = allProducts.filter((product) => product.ownerCode === code);
      const placeholder = new Option(ownerNames[code] ? 'Ketik atau pilih barang' : 'Masukkan ID pemilik terlebih dahulu', '');

      productSelect.replaceChildren(placeholder, ...matches.map((product) => {
        const option = new Option(product.label, product.id);
        option.dataset.ownerCode = product.ownerCode;
        option.dataset.unit = product.unit;

        return option;
      }));

      return matches.length;
    };

    const updateOwner = () => {
      const code = ownerCode();
      const ownerChanged = code !== currentOwnerCode;
      currentOwnerCode = code;

      ownerNameField.value = ownerNames[code] || '';
      ownerName.textContent = ownerNames[code] ? 'Nama pemilik terisi otomatis dan tidak dapat diedit.' : (ownerInput.value ? 'ID tidak ditemukan. Tambahkan nama di Master ID Nama.' : 'Masukkan ID dari Master ID Nama.');
      productSelect.disabled = !ownerNames[code];

      const availableCount = ownerNames[code] ? allProducts.filter((product) => product.ownerCode === code).length : 0;
      stockHint.textContent = ownerNames[code]
        ? (availableCount > 0 ? 'Pilih barang dari stok milik ' + ownerNames[code] + '.' : 'Belum ada stok untuk pemilik ini. Catat Pembelian terlebih dahulu.')
        : 'Pilih pemilik untuk melihat stok barangnya.';

      if (!ownerChanged) {
        return;
      }

      const hadSelect2 = Boolean(window.jQuery?.fn?.select2 && window.jQuery(productSelect).data('select2'));
      if (hadSelect2) {
        window.jQuery(productSelect).select2('destroy');
      }

      rebuildProductSelect(code);
      initSelect2();
      unitInput.value = '';
    };

    ownerInput.addEventListener('input', updateOwner);
    ownerInput.addEventListener('blur', () => {
      if (ownerInput.value.trim()) {
        ownerInput.value = ownerInput.value.trim().padStart(3, '0');
      }
      updateOwner();
    });

    const syncUnit = () => {
      unitInput.value = productSelect.selectedOptions[0]?.dataset.unit || '';
    };

    if (window.jQuery) {
      window.jQuery(productSelect).on('change', syncUnit);
    } else {
      productSelect.addEventListener('change', syncUnit);
    }

    priceDisplay.addEventListener('input', () => {
      const start = priceDisplay.selectionStart;
      const oldValue = priceDisplay.value;
      const formatted = formatPrice(oldValue);
      const delta = formatted.length - oldValue.length;
      priceDisplay.value = formatted;
      priceDisplay.setSelectionRange(Math.max(0, start + delta), Math.max(0, start + delta));
      updateTotal();
    });

    quantityInput.addEventListener('input', updateTotal);
    updateOwner();
    const initialProductId = @json(old('produk_id'));
    if (initialProductId) {
      productSelect.value = String(initialProductId);
      unitInput.value = productSelect.selectedOptions[0]?.dataset.unit || '';

      if (window.jQuery?.fn?.select2) {
        window.jQuery(productSelect).trigger('change.select2');
      }
    }
    updateTotal();
  })();
</script>
@endpush

@extends('layouts.modernize')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
  <style>{!! file_get_contents(public_path('assets/css/dashboard.css')) !!}</style>
@endpush

@section('content')
  <div class="bmt-dashboard">
    <!-- Filter periode -->
    <div class="dash-toolbar">
      <div class="dash-toolbar-meta">
        <div>
          <div class="dash-toolbar-eyebrow">Periode laporan</div>
          <div class="dash-toolbar-period">{{ $periodeLabel }}</div>
        </div>
      </div>
      <form method="GET" action="{{ route('dashboard') }}" class="dash-filter">
        <div class="dash-filter-field">
          <label for="tanggal_mulai">Dari</label>
          <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="{{ $tanggalMulai }}">
        </div>
        <div class="dash-filter-field">
          <label for="tanggal_selesai">Sampai</label>
          <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai" value="{{ $tanggalSelesai }}"
            min="{{ $tanggalMulai }}">
        </div>
        <button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1"></i>Terapkan</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Reset</a>
      </form>
    </div>

    <!-- Total penjualan, pembelian, dan stok akhir -->
    <div class="row g-3">
      <div class="col-md-4 d-flex">
        <div class="dash-kpi dash-kpi--sales w-100">
          <div class="dash-kpi-head">
            <span class="dash-kpi-label">Total Penjualan</span>
            <span class="dash-kpi-icon"><i class="ti ti-arrow-up-right"></i></span>
          </div>
          <div class="dash-kpi-value">Rp {{ number_format($totalSales, 0, ',', '.') }}</div>
          <div class="dash-kpi-foot">{{ $periodeLabel }}</div>
        </div>
      </div>
      <div class="col-md-4 d-flex">
        <div class="dash-kpi dash-kpi--purchases w-100">
          <div class="dash-kpi-head">
            <span class="dash-kpi-label">Total Pembelian</span>
            <span class="dash-kpi-icon"><i class="ti ti-arrow-down-left"></i></span>
          </div>
          <div class="dash-kpi-value">Rp {{ number_format($totalPurchases, 0, ',', '.') }}</div>
          <div class="dash-kpi-foot">{{ $periodeLabel }}</div>
        </div>
      </div>
      <div class="col-md-4 d-flex">
        <div class="dash-kpi dash-kpi--stock w-100">
          <div class="dash-kpi-head">
            <span class="dash-kpi-label">Nilai Stok Akhir</span>
            <span class="dash-kpi-icon"><i class="ti ti-packages"></i></span>
          </div>
          <div class="dash-kpi-value">Rp {{ number_format($totalNilaiStok, 0, ',', '.') }}</div>
          <div class="dash-kpi-foot">{{ $totalItemStok }} item belum terjual · per {{ $stokPerTanggal }}</div>
        </div>
      </div>
    </div>

    <!-- Grafik dan ringkasan -->
    <div class="row g-3 mt-1">
      <div class="col-xl-8 d-flex">
        <div class="dash-card dash-chart-card w-100">
          <div class="dash-card-body">
            <div class="dash-card-head">
              <div>
                <h5 class="dash-card-title">{{ $dashboardData['chartTitle'] }}</h5>
                <p class="dash-card-sub">{{ $dashboardData['periodeLabel'] }}</p>
              </div>
            </div>
            <div id="chart"></div>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="d-flex flex-column gap-3 h-100">
          <!-- Ringkasan penjualan -->
          <div class="dash-card">
            <div class="dash-card-body">
              <div class="dash-card-head mb-2">
                <div>
                  <h5 class="dash-card-title">Ringkasan Penjualan</h5>
                  <p class="dash-card-sub">{{ $dashboardData['periodeLabel'] }}</p>
                </div>
              </div>
              <div class="dash-summary">
                <div class="dash-summary-value">Rp {{ number_format($dashboardData['yearSales'], 0, ',', '.') }}</div>
                <div id="breakup"></div>
              </div>
              <div class="dash-summary-meta">
                <span><span class="dash-dot dash-dot--sales"></span>Modal Rp {{ number_format($dashboardData['yearCost'], 0, ',', '.') }}</span>
                <span><span class="dash-dot dash-dot--profit"></span>{{ $dashboardData['yearProfit'] < 0 ? 'Rugi' : 'Laba' }} Rp {{ number_format(abs($dashboardData['yearProfit']), 0, ',', '.') }}</span>
              </div>
            </div>
          </div>

          <!-- Laba / rugi -->
          <div class="dash-card flex-grow-1">
            <div class="dash-card-body">
              <div class="dash-card-head mb-2">
                <div>
                  <h5 class="dash-card-title">Laba / Rugi</h5>
                  <p class="dash-card-sub">{{ $dashboardData['periodeLabel'] }}</p>
                </div>
                <span class="dash-kpi-icon {{ $dashboardData['monthProfit'] < 0 ? 'dash-kpi-icon--danger' : '' }}"><i class="ti ti-report-money"></i></span>
              </div>
              <div class="dash-summary-value {{ $dashboardData['monthProfit'] < 0 ? 'is-negative' : '' }}">Rp {{ number_format($dashboardData['monthProfit'], 0, ',', '.') }}</div>
              <div id="earning"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Ringkasan omset per toko -->
    <div class="dash-card mt-3">
      <div class="dash-card-body">
        <div class="dash-card-head dash-card-head--store">
          <div>
            <h5 class="dash-card-title">Ringkasan Omset per Toko</h5>
            <p class="dash-card-sub">Penjualan dan pembelian per toko · {{ $periodeLabel }}</p>
          </div>
          <div class="dash-table-tools">
            <label class="visually-hidden" for="omset-toko-search">Cari toko</label>
            <div class="dash-search">
              <i class="ti ti-search" aria-hidden="true"></i>
              <input type="search" id="omset-toko-search" class="form-control" placeholder="Cari ID atau nama toko"
                autocomplete="off">
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table id="omset-toko-table" class="table dash-table align-middle mb-0">
            <thead>
              <tr>
                <th>No.</th>
                <th>ID</th>
                <th>Nama Toko</th>
                <th class="num">Penjualan</th>
                <th class="num">Pembelian</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($storeSummaries as $store)
                <tr data-store-row data-store-search="{{ $store['id'] }} {{ $store['nama'] }}">
                  <td class="muted" data-store-number>{{ $loop->iteration }}</td>
                  <td class="muted">{{ $store['id'] }}</td>
                  <td class="strong">{{ $store['nama'] }}</td>
                  <td class="num">Rp {{ number_format($store['sales'], 0, ',', '.') }}</td>
                  <td class="num">Rp {{ number_format($store['purchases'], 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr data-store-empty><td colspan="5" class="text-center text-muted py-4">Belum ada toko terdaftar.</td></tr>
              @endforelse
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3">TOTAL SEMUA TOKO</td>
                <td class="num">Rp {{ number_format($totalSales, 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format($totalPurchases, 0, ',', '.') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Stok akhir / barang belum terjual -->
    <div class="dash-card mt-3">
      <div class="dash-card-body">
        <div class="dash-card-head dash-card-head--stock">
          <div>
            <h5 class="dash-card-title">Stok Akhir (Barang Belum Terjual)</h5>
            <p class="dash-card-sub">Sisa stok per {{ $stokPerTanggal }} · {{ $totalItemStok }} item</p>
          </div>
          <div class="dash-table-tools">
            <label class="visually-hidden" for="stok-akhir-search">Cari stok akhir</label>
            <div class="dash-search">
              <i class="ti ti-search" aria-hidden="true"></i>
              <input type="search" id="stok-akhir-search" class="form-control" placeholder="Cari barang, toko, atau satuan"
                autocomplete="off">
            </div>
            <a href="{{ route('laporan.stok-akhir') }}" class="dash-link">Lihat semua <i class="ti ti-arrow-right"></i></a>
          </div>
        </div>
        <div class="dash-stock-scroll">
          <table id="stok-akhir-table" class="table dash-table align-middle mb-0">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nama Barang</th>
                <th>Toko</th>
                <th>Satuan</th>
                <th class="num">Stok Akhir</th>
                <th class="num">Harga Beli</th>
                <th class="num">Nilai Stok</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($stokAkhirs as $item)
                <tr data-stock-row data-stock-search="{{ $item['nama'] }} {{ $item['toko'] }} {{ $item['satuan'] }}">
                  <td class="muted" data-stock-number>{{ $loop->iteration }}</td>
                  <td class="strong">{{ $item['nama'] }}</td>
                  <td>{{ $item['toko'] }}</td>
                  <td class="muted">{{ $item['satuan'] ?: '-' }}</td>
                  <td class="num">{{ rtrim(rtrim(number_format($item['stok'], 3, ',', '.'), '0'), ',') }}</td>
                  <td class="num">Rp {{ number_format($item['harga_beli'], 0, ',', '.') }}</td>
                  <td class="num strong">Rp {{ number_format($item['nilai'], 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr data-stock-empty><td colspan="7" class="text-center text-muted py-4">Semua barang sudah terjual / belum ada stok.</td></tr>
              @endforelse
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6">TOTAL NILAI STOK AKHIR</td>
                <td class="num">Rp {{ number_format($totalNilaiStok, 0, ',', '.') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Aktivitas dan transaksi terbaru -->
    <div class="row g-3 mt-1">
      <div class="col-xl-4 d-flex">
        <div class="dash-card w-100">
          <div class="dash-card-body">
            <div class="dash-card-head mb-3">
              <div>
                <h5 class="dash-card-title">Aktivitas Transaksi</h5>
                <p class="dash-card-sub">Transaksi terbaru pada periode ini</p>
              </div>
            </div>
            <ul class="timeline-widget mb-0 position-relative">
              @forelse ($recentTransactions as $transaction)
                <li class="timeline-item d-flex position-relative overflow-hidden">
                  <div class="timeline-time flex-shrink-0 text-end">{{ $transaction['date']->format('d/m') }}</div>
                  <div class="timeline-badge-wrap d-flex flex-column align-items-center">
                    <span class="timeline-badge border-2 border border-{{ $transaction['type'] === 'Penjualan' ? 'primary' : 'danger' }} flex-shrink-0 my-8"></span>
                    @if (!$loop->last)<span class="timeline-badge-border d-block flex-shrink-0"></span>@endif
                  </div>
                  <div class="timeline-desc mt-n1">
                    <span class="fw-semibold">{{ $transaction['type'] }}</span> {{ $transaction['name'] }}
                    <span class="d-block text-muted">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</span>
                  </div>
                </li>
              @empty
                <li class="text-muted">Belum ada transaksi.</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>
      <div class="col-xl-8 d-flex">
        <div class="dash-card w-100">
          <div class="dash-card-body">
            <div class="dash-card-head">
              <div>
                <h5 class="dash-card-title">Transaksi Terbaru</h5>
                <p class="dash-card-sub">Penjualan dan pembelian terakhir</p>
              </div>
              <a href="{{ route('penjualan.index') }}" class="dash-link">Lihat semua <i class="ti ti-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
              <table class="table dash-table text-nowrap align-middle mb-0">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Tgl</th>
                    <th>Nama Barang</th>
                    <th>Transaksi</th>
                    <th class="num">Unit</th>
                    <th class="num">Total</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($recentTransactions as $transaction)
                    <tr>
                      <td class="muted">{{ $loop->iteration }}</td>
                      <td>{{ $transaction['date']->format('d/m/Y') }}</td>
                      <td>
                        <span class="strong">{{ $transaction['name'] }}</span>@if (filled($transaction['unit'])) <span class="text-muted">({{ $transaction['unit'] }})</span>@endif
                      </td>
                      <td><span class="badge {{ $transaction['type'] === 'Penjualan' ? 'bg-primary' : 'bg-danger' }} rounded-pill fw-semibold">{{ $transaction['type'] }}</span></td>
                      <td class="num">{{ rtrim(rtrim(number_format($transaction['quantity'], 3, ',', '.'), '0'), ',') }}</td>
                      <td class="num strong">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>{!! file_get_contents(public_path('assets/js/dashboard-search.js')) !!}</script>
  <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
  <script>window.dashboardData = @json($dashboardData);</script>
  <script src="{{ asset('assets/js/dashboard.js') }}?v={{ filemtime(public_path('assets/js/dashboard.js')) }}"></script>
@endpush
